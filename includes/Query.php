<?php
/**
 * GROQ-like query endpoint for Portable Text content.
 *
 * Provides a REST API endpoint that can query into the PT JSON
 * structure stored in post_content across multiple posts.
 *
 * @package WPPortableText
 */

declare(strict_types=1);

namespace WPPortableText;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Custom REST endpoint for querying Portable Text content.
 */
class Query {

	/**
	 * REST API namespace.
	 */
	private const stringNAMESPACE = 'wp-portable-text/v1';

	/**
	 * Allowed block types for the block_type parameter.
	 *
	 * @var string[]
	 */
	private const array BLOCK_TYPES = [
		'block',
		'image',
		'codeBlock',
		'embed',
		'table',
		'break',
	];

	/**
	 * Maximum items per page.
	 */
	private const int MAX_PER_PAGE = 100;

	/**
	 * Default cache TTL in seconds (5 minutes).
	 */
	private const int CACHE_TTL = 300;

	/**
	 * Cache group prefix for transient keys.
	 */
	private const string CACHE_PREFIX = 'wp_pt_query_';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'save_post', [ $this, 'flush_cache' ] );
		add_action( 'delete_post', [ $this, 'flush_cache' ] );
		add_action( 'wp_trash_post', [ $this, 'flush_cache' ] );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE ,
			'/query',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_query' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_query_args(),
			]
		);

		register_rest_route(
			self::NAMESPACE ,
			'/blocks',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_blocks' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_blocks_args(),
			]
		);
	}

	/**
	 * Handle /query — find posts that contain specific block types or properties.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_query( WP_REST_Request $request ) {
		$cache_key = $this->cache_key( 'query', $request );
		$cached    = $this->cache_get( $cache_key );

		if ( false !== $cached ) {
			$response = new WP_REST_Response( $cached[ 'data' ] );
			foreach ( $cached[ 'headers' ] as $name => $value ) {
				$response->header( $name, $value );
			}
			$response->header( 'X-WP-PT-Cache', 'HIT' );
			return $response;
		}

		$post_type  = $request->get_param( 'post_type' ) ?? 'post';
		$block_type = $request->get_param( 'block_type' );
		$has        = $request->get_param( 'has' );
		$style      = $request->get_param( 'style' );
		$per_page   = (int) ( $request->get_param( 'per_page' ) ?? 10 );
		$page       = (int) ( $request->get_param( 'page' ) ?? 1 );

		$query_args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query = $this->create_wp_query( $query_args );
		$posts = $query->posts;

		$results = [];

		foreach ( $posts as $post ) {
			if ( ! is_string( $post->post_content ) || '' === $post->post_content ) {
				continue;
			}

			$decoded = json_decode( $post->post_content, true );
			if ( ! is_array( $decoded ) || ! isset( $decoded[ 0 ][ '_type' ] ) ) {
				continue;
			}

			$matched_blocks = $this->filter_blocks( $decoded, $block_type, $has, $style );

			if ( null !== $block_type || null !== $has || null !== $style ) {
				if ( empty( $matched_blocks ) ) {
					continue;
				}
			}

			$results[] = [
				'id'             => $post->ID,
				'title'          => get_the_title( $post ),
				'date'           => $post->post_date_gmt,
				'link'           => get_permalink( $post ),
				'portable_text'  => $decoded,
				'matched_blocks' => ( null !== $block_type || null !== $has || null !== $style )
					? $matched_blocks
					: null,
			];
		}

		$response = new WP_REST_Response( $results );

		$headers = [
			'X-WP-Total'      => (string) $query->found_posts,
			'X-WP-TotalPages' => (string) $query->max_num_pages,
		];

		foreach ( $headers as $name => $value ) {
			$response->header( $name, $value );
		}
		$response->header( 'X-WP-PT-Cache', 'MISS' );

		$this->cache_set( $cache_key, [
			'data'    => $results,
			'headers' => $headers,
		] );

		return $response;
	}

	/**
	 * Handle /blocks — extract specific block types across posts.
	 *
	 * Returns a flat list of blocks (with post context) matching the criteria.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_blocks( WP_REST_Request $request ) {
		$cache_key = $this->cache_key( 'blocks', $request );
		$cached    = $this->cache_get( $cache_key );

		if ( false !== $cached ) {
			$response = new WP_REST_Response( $cached[ 'data' ] );
			foreach ( $cached[ 'headers' ] as $name => $value ) {
				$response->header( $name, $value );
			}
			$response->header( 'X-WP-PT-Cache', 'HIT' );
			return $response;
		}

		$post_type  = $request->get_param( 'post_type' ) ?? 'post';
		$block_type = $request->get_param( 'block_type' );
		$language   = $request->get_param( 'language' );
		$style      = $request->get_param( 'style' );
		$per_page   = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$page       = (int) ( $request->get_param( 'page' ) ?? 1 );

		// Fetch more posts than needed since we filter by block content.
		$query_args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query  = $this->create_wp_query( $query_args );
		$blocks = [];

		foreach ( $query->posts as $post ) {
			if ( ! is_string( $post->post_content ) || '' === $post->post_content ) {
				continue;
			}

			$decoded = json_decode( $post->post_content, true );
			if ( ! is_array( $decoded ) || ! isset( $decoded[ 0 ][ '_type' ] ) ) {
				continue;
			}

			foreach ( $decoded as $block ) {
				if ( ! $this->block_matches( $block, $block_type, null, $style ) ) {
					continue;
				}

				// Filter by language for code blocks.
				if ( null !== $language && ( $block[ 'language' ] ?? '' ) !== $language ) {
					continue;
				}

				$blocks[] = [
					'block'   => $block,
					'post_id' => $post->ID,
					'title'   => get_the_title( $post ),
					'link'    => get_permalink( $post ),
				];
			}
		}

		$total  = count( $blocks );
		$offset = ( $page - 1 ) * $per_page;
		$paged  = array_slice( $blocks, $offset, $per_page );

		$response = new WP_REST_Response( $paged );

		$headers = [
			'X-WP-Total'      => (string) $total,
			'X-WP-TotalPages' => (string) max( 1, (int) ceil( $total / $per_page ) ),
		];

		foreach ( $headers as $name => $value ) {
			$response->header( $name, $value );
		}
		$response->header( 'X-WP-PT-Cache', 'MISS' );

		$this->cache_set( $cache_key, [
			'data'    => $paged,
			'headers' => $headers,
		] );

		return $response;
	}

	/**
	 * Create a WP_Query instance. Extracted for testability.
	 *
	 * @param array<string,mixed> $args WP_Query arguments.
	 * @return object WP_Query-like object with posts, found_posts, max_num_pages.
	 */
	protected function create_wp_query( array $args ): object {
		return new \WP_Query( $args );
	}

	/**
	 * Build a cache key from request parameters.
	 *
	 * @param string               $endpoint 'query' or 'blocks'.
	 * @param WP_REST_Request      $request  Current request.
	 * @return string Transient key (max 172 chars with prefix).
	 */
	private function cache_key( string $endpoint, WP_REST_Request $request ): string {
		$params = $request->get_query_params();
		ksort( $params );
		$hash = md5( $endpoint . '|' . wp_json_encode( $params ) );
		return self::CACHE_PREFIX . $hash;
	}

	/**
	 * Get cached response data.
	 *
	 * @param string $key Transient key.
	 * @return array{data: mixed, headers: array<string,string>}|false Cached data or false.
	 */
	protected function cache_get( string $key ) {
		return get_transient( $key );
	}

	/**
	 * Store response data in cache.
	 *
	 * @param string                          $key     Transient key.
	 * @param array{data: mixed, headers: array<string,string>} $value   Data to cache.
	 * @return void
	 */
	protected function cache_set( string $key, array $value ): void {
		$ttl = (int) apply_filters( 'wp_portable_text_query_cache_ttl', self::CACHE_TTL );
		if ( $ttl > 0 ) {
			set_transient( $key, $value, $ttl );
		}
	}

	/**
	 * Flush all query caches.
	 *
	 * Deletes transients matching the cache prefix. Called on post save/delete.
	 *
	 * @param int $post_id Post ID (unused but required by hook signature).
	 * @return void
	 */
	public function flush_cache( int $post_id = 0 ): void {
		global $wpdb;
		$prefix = '_transient_' . self::CACHE_PREFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
				$prefix . '%',
				'_transient_timeout_' . self::CACHE_PREFIX . '%'
			)
		);
	}

	/**
	 * Filter blocks by type, annotation presence, and style.
	 *
	 * @param array<int,array<string,mixed>> $blocks     All blocks.
	 * @param string|null                    $block_type Filter by _type.
	 * @param string|null                    $has        Filter by annotation/mark presence.
	 * @param string|null                    $style      Filter by block style.
	 * @return array<int,array<string,mixed>> Matching blocks.
	 */
	private function filter_blocks( array $blocks, ?string $block_type, ?string $has, ?string $style ): array {
		$matched = [];

		foreach ( $blocks as $block ) {
			if ( $this->block_matches( $block, $block_type, $has, $style ) ) {
				$matched[] = $block;
			}
		}

		return $matched;
	}

	/**
	 * Check if a single block matches the given criteria.
	 *
	 * @param array<string,mixed> $block      Single block.
	 * @param string|null         $block_type Required _type.
	 * @param string|null         $has        Required annotation/mark.
	 * @param string|null         $style      Required style.
	 * @return bool
	 */
	private function block_matches( array $block, ?string $block_type, ?string $has, ?string $style ): bool {
		// Filter by _type.
		if ( null !== $block_type && ( $block[ '_type' ] ?? '' ) !== $block_type ) {
			return false;
		}

		// Filter by style.
		if ( null !== $style && ( $block[ 'style' ] ?? '' ) !== $style ) {
			return false;
		}

		// Filter by annotation/mark presence.
		if ( null !== $has ) {
			if ( ! $this->block_has_feature( $block, $has ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if a block contains a specific feature (annotation type or mark).
	 *
	 * @param array<string,mixed> $block   Block data.
	 * @param string              $feature Feature name (e.g., 'link', 'strong', 'em').
	 * @return bool
	 */
	private function block_has_feature( array $block, string $feature ): bool {
		// Check markDefs for annotations (e.g., 'link').
		$mark_defs = $block[ 'markDefs' ] ?? [];
		foreach ( $mark_defs as $def ) {
			if ( ( $def[ '_type' ] ?? '' ) === $feature ) {
				return true;
			}
		}

		// Check children spans for decorator marks (e.g., 'strong', 'em').
		$children = $block[ 'children' ] ?? [];
		foreach ( $children as $child ) {
			$marks = $child[ 'marks' ] ?? [];
			if ( in_array( $feature, $marks, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get argument definitions for the /query endpoint.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_query_args(): array {
		return [
			'post_type'  => [
				'type'              => 'string',
				'default'           => 'post',
				'sanitize_callback' => 'sanitize_key',
			],
			'block_type' => [
				'type'              => 'string',
				'enum'              => self::BLOCK_TYPES,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'has'        => [
				'type'              => 'string',
				'description'       => 'Filter posts containing blocks with this annotation or mark (e.g. link, strong, em).',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'style'      => [
				'type'              => 'string',
				'description'       => 'Filter by block style (e.g. h1, h2, blockquote, normal).',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page'   => [
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			],
			'page'       => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Get argument definitions for the /blocks endpoint.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_blocks_args(): array {
		return [
			'post_type'  => [
				'type'              => 'string',
				'default'           => 'post',
				'sanitize_callback' => 'sanitize_key',
			],
			'block_type' => [
				'type'              => 'string',
				'required'          => true,
				'enum'              => self::BLOCK_TYPES,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'language'   => [
				'type'              => 'string',
				'description'       => 'Filter code blocks by language.',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'style'      => [
				'type'              => 'string',
				'description'       => 'Filter by block style.',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page'   => [
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			],
			'page'       => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			],
		];
	}
}
