<?php
/**
 * Block type archive pages — /block/{type}/ frontend.
 *
 * Renders archive-like pages listing all blocks of a given type
 * across published posts, with links back to the source post.
 *
 * @package WPPortableText
 */

declare(strict_types=1);

namespace WPPortableText;

use WP_Query;

/**
 * Registers rewrite rules and renders block-type archive pages.
 */
class Block_Archive {

	/**
	 * Query variable for the block type.
	 */
	public const string QUERY_VAR = 'pt_block_type';

	/**
	 * Items per page.
	 */
	private const int PER_PAGE = 20;

	/**
	 * Human-readable labels for block types.
	 *
	 * @var array<string,string>
	 */
	private const array LABELS = [
		'block'     => 'Text Blocks',
		'image'     => 'Images',
		'codeBlock' => 'Code Blocks',
		'embed'     => 'Embeds',
		'table'     => 'Tables',
		'break'     => 'Breaks',
	];

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'render' ] );
		add_filter( 'document_title_parts', [ $this, 'filter_title' ] );
	}

	/**
	 * Register rewrite rules for /block/ and /block/{type}/.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^block/([^/]+)/page/([0-9]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]&paged=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^block/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^block/?$',
			'index.php?' . self::QUERY_VAR . '=index',
			'top'
		);
	}

	/**
	 * Add pt_block_type to recognized query variables.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Render the block archive page when pt_block_type is set.
	 */
	public function render(): void {
		$block_type = get_query_var( self::QUERY_VAR );
		if ( '' === $block_type ) {
			return;
		}

		if ( 'index' === $block_type ) {
			$this->render_index();
			exit;
		}

		if ( ! in_array( $block_type, Query::BLOCK_TYPES, true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		$page     = max( 1, (int) get_query_var( 'paged', 1 ) );
		$blocks   = $this->collect_blocks( $block_type );
		$total    = count( $blocks );
		$offset   = ( $page - 1 ) * self::PER_PAGE;
		$paged    = array_slice( $blocks, $offset, self::PER_PAGE );
		$pages    = (int) ceil( $total / self::PER_PAGE );
		$label    = self::LABELS[ $block_type ] ?? $block_type;
		$renderer = new Renderer();

		status_header( 200 );
		get_header();

		echo '<div class="wp-portable-text-block-archive" style="max-width:800px;margin:2rem auto;padding:0 1rem">';
		printf( '<h1>%s</h1>', esc_html( $label ) );
		printf(
			'<p class="archive-description">%s</p>',
			esc_html( sprintf( '%d %s found across all posts.', $total, strtolower( $label ) ) )
		);

		if ( empty( $paged ) ) {
			echo '<p>No blocks found.</p>';
		} else {
			echo '<div class="block-archive-list">';
			foreach ( $paged as $item ) {
				echo '<article class="block-archive-item" style="margin-bottom:2rem;padding-bottom:2rem;border-bottom:1px solid #ddd">';

				// Render the block as HTML.
				$html = $renderer->blocks_to_html( [ $item['block'] ] );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by Renderer with proper escaping.
				echo '<div class="block-content">' . $html . '</div>';

				// Source link.
				printf(
					'<p class="block-source" style="font-size:0.85em;color:#666;margin-top:0.5rem">From: <a href="%s">%s</a></p>',
					esc_url( $item['link'] ),
					esc_html( $item['title'] )
				);

				echo '</article>';
			}
			echo '</div>';

			$this->render_pagination( $block_type, $page, $pages );
		}

		echo '</div>';

		get_footer();
		exit;
	}

	/**
	 * Render the /block/ index page listing all block types.
	 */
	private function render_index(): void {
		$counts = $this->count_all_types();

		status_header( 200 );
		get_header();

		echo '<div class="wp-portable-text-block-archive" style="max-width:800px;margin:2rem auto;padding:0 1rem">';
		echo '<h1>Block Type Archives</h1>';
		echo '<p class="archive-description">Browse all Portable Text content by block type.</p>';
		echo '<ul class="block-type-list" style="list-style:none;padding:0">';

		foreach ( Query::BLOCK_TYPES as $type ) {
			$label = self::LABELS[ $type ] ?? $type;
			$count = $counts[ $type ] ?? 0;
			$url   = home_url( '/block/' . $type . '/' );

			printf(
				'<li style="margin-bottom:1rem;padding:1rem;border:1px solid #ddd;border-radius:4px">'
				. '<a href="%s" style="text-decoration:none;font-size:1.2em;font-weight:600">%s</a>'
				. ' <span style="color:#666">(%d)</span></li>',
				esc_url( $url ),
				esc_html( $label ),
				$count
			);
		}

		echo '</ul>';
		echo '</div>';

		get_footer();
	}

	/**
	 * Filter <title> tag for block archive pages.
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function filter_title( array $parts ): array {
		$block_type = get_query_var( self::QUERY_VAR );
		if ( '' === $block_type ) {
			return $parts;
		}

		if ( 'index' === $block_type ) {
			$parts['title'] = 'Block Type Archives';
		} elseif ( in_array( $block_type, Query::BLOCK_TYPES, true ) ) {
			$parts['title'] = self::LABELS[ $block_type ] ?? $block_type;
		}

		return $parts;
	}

	/**
	 * Collect all blocks of a given type across published posts.
	 *
	 * @param string $block_type Target block _type.
	 * @return array<int,array{block:array<string,mixed>,post_id:int,title:string,link:string}>
	 */
	private function collect_blocks( string $block_type ): array {
		$query = new WP_Query( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$blocks = [];

		foreach ( $query->posts as $post ) {
			if ( ! is_string( $post->post_content ) || '' === $post->post_content ) {
				continue;
			}

			$decoded = json_decode( $post->post_content, true );
			if ( ! is_array( $decoded ) || empty( $decoded ) || ! isset( $decoded[0]['_type'] ) ) {
				continue;
			}

			foreach ( $decoded as $block ) {
				if ( ( $block['_type'] ?? '' ) !== $block_type ) {
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

		return $blocks;
	}

	/**
	 * Count blocks of each type for the index page.
	 *
	 * @return array<string,int>
	 */
	private function count_all_types(): array {
		$query = new WP_Query( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$counts = array_fill_keys( Query::BLOCK_TYPES, 0 );

		foreach ( $query->posts as $post ) {
			if ( ! is_string( $post->post_content ) || '' === $post->post_content ) {
				continue;
			}

			$decoded = json_decode( $post->post_content, true );
			if ( ! is_array( $decoded ) || empty( $decoded ) || ! isset( $decoded[0]['_type'] ) ) {
				continue;
			}

			foreach ( $decoded as $block ) {
				$type = $block['_type'] ?? '';
				if ( isset( $counts[ $type ] ) ) {
					++$counts[ $type ];
				}
			}
		}

		return $counts;
	}

	/**
	 * Render pagination links.
	 *
	 * @param string $block_type Current block type.
	 * @param int    $current    Current page number.
	 * @param int    $total      Total pages.
	 */
	private function render_pagination( string $block_type, int $current, int $total ): void {
		if ( $total <= 1 ) {
			return;
		}

		$base = home_url( '/block/' . $block_type . '/' );

		echo '<nav class="block-archive-pagination" style="margin-top:2rem;text-align:center">';

		if ( $current > 1 ) {
			$prev = 2 === $current ? $base : $base . 'page/' . ( $current - 1 ) . '/';
			printf( '<a href="%s" style="margin:0 0.5rem">&laquo; Previous</a>', esc_url( $prev ) );
		}

		for ( $i = 1; $i <= $total; $i++ ) {
			$url = 1 === $i ? $base : $base . 'page/' . $i . '/';
			if ( $i === $current ) {
				printf( '<strong style="margin:0 0.25rem">%d</strong>', $i );
			} else {
				printf( '<a href="%s" style="margin:0 0.25rem">%d</a>', esc_url( $url ), $i );
			}
		}

		if ( $current < $total ) {
			$next = $base . 'page/' . ( $current + 1 ) . '/';
			printf( '<a href="%s" style="margin:0 0.5rem">Next &raquo;</a>', esc_url( $next ) );
		}

		echo '</nav>';
	}

	/**
	 * Flush rewrite rules (call on activation).
	 */
	public static function flush_rules(): void {
		( new self() )->add_rewrite_rules();
		flush_rewrite_rules();
	}
}
