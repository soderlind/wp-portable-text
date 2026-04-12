<?php
/**
 * Frontend renderer — converts PT JSON stored in post_content to HTML.
 *
 * Lightweight PHP renderer for Portable Text. Does not depend on sanity-php.
 * The PT spec is simple enough to walk directly.
 *
 * @package WPPortableText
 */

declare(strict_types=1);

namespace WPPortableText;

use WPPortableText\Serializers\Html_Serializer;
use WPPortableText\Serializers\Markdown_Serializer;
use WPPortableText\Serializers\Serializer;

/**
 * Renders Portable Text JSON to HTML on the frontend via the_content filter.
 */
class Renderer {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Run early (priority 5) so shortcodes/embeds/etc. can still process.
		add_filter( 'the_content', [ $this, 'render' ], 5 );

		// Provide PT-aware excerpt generation.
		add_filter( 'wp_trim_excerpt', [ $this, 'trim_excerpt' ], 5, 2 );

		// Show rendered HTML in revision diffs instead of raw JSON.
		add_filter( '_wp_post_revision_field_post_content', [ $this, 'render_revision_field' ], 10, 4 );

		// REST API: expose PT JSON alongside rendered HTML.
		add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );

		// Markdown alternate: <link> tag and content serving.
		add_action( 'wp_head', [ $this, 'render_markdown_link' ] );
		add_action( 'template_redirect', [ $this, 'serve_markdown' ] );
	}

	/**
	 * Filter the_content: if post_content is PT JSON, render to HTML.
	 *
	 * @param string $content Post content.
	 * @return string HTML or original content.
	 */
	public function render( string $content ): string {
		$decoded = json_decode( $content, true );

		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return $content;
		}

		// Quick check: first element must have _type.
		if ( ! isset( $decoded[ 0 ][ '_type' ] ) ) {
			return $content;
		}

		return $this->blocks_to_html( $decoded );
	}

	/**
	 * Convert PT JSON to HTML for the revision diff screen.
	 *
	 * WordPress passes the raw post_content through this filter before
	 * computing the text diff. By rendering PT JSON to HTML here, the
	 * revision comparison shows readable content instead of raw JSON.
	 *
	 * @param string       $value    The field value (post_content).
	 * @param string       $field    The field name.
	 * @param \WP_Post|false $post   The revision post object.
	 * @param string       $context  'from' or 'to'.
	 * @return string
	 */
	public function render_revision_field( $value, $field = '', $post = false, $context = '' ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return (string) $value;
		}

		$decoded = json_decode( $value, true );

		if ( is_array( $decoded ) && ! empty( $decoded ) && isset( $decoded[ 0 ][ '_type' ] ) ) {
			return $this->blocks_to_html( $decoded );
		}

		return $value;
	}

	/**
	 * Generate excerpt from PT JSON.
	 *
	 * @param string $text      Trimmed excerpt.
	 * @param string $raw_excerpt Raw excerpt (manual excerpt if set).
	 * @return string
	 */
	public function trim_excerpt( string $text, string $raw_excerpt ): string {
		if ( '' !== $raw_excerpt ) {
			return $text;
		}

		$post = get_post();
		if ( ! $post || ! is_string( $post->post_content ) || '' === $post->post_content ) {
			return $text;
		}

		$decoded = json_decode( $post->post_content, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded[ 0 ][ '_type' ] ) ) {
			return $text;
		}

		$content_filter = new Content_Filter();
		$plaintext      = $content_filter->extract_plaintext( $decoded );

		/** This filter is documented in wp-includes/formatting.php */
		$plaintext = apply_filters( 'the_content', $plaintext );
		$plaintext = str_replace( ']]>', ']]&gt;', $plaintext );

		$excerpt_length = (int) apply_filters( 'excerpt_length', 55 );
		$excerpt_more   = apply_filters( 'excerpt_more', ' [&hellip;]' );

		return wp_trim_words( $plaintext, $excerpt_length, $excerpt_more );
	}

	/**
	 * Register REST API fields for PT data.
	 */
	public function register_rest_fields(): void {
		$post_types = get_post_types( [ 'show_in_rest' => true ] );

		foreach ( $post_types as $post_type ) {
			register_rest_field(
				$post_type,
				'portable_text',
				[
					'get_callback'    => static function ( array $object ): ?array {
						$post = get_post( $object[ 'id' ] ?? 0 );
						if ( ! $post || ! is_string( $post->post_content ) || '' === $post->post_content ) {
							return null;
						}

						$decoded = json_decode( $post->post_content, true );

						if ( is_array( $decoded ) && isset( $decoded[ 0 ][ '_type' ] ) ) {
							return $decoded;
						}

						return null;
					},
					'update_callback' => [ $this, 'update_portable_text' ],
					'schema'          => [
						'description' => 'Portable Text JSON representation of the content.',
						'type'        => [ 'array', 'null' ],
					],
				]
			);
		}
	}

	/**
	 * Update post_content with Portable Text JSON via REST API.
	 *
	 * Validates the structure, writes JSON directly to post_content
	 * (bypassing kses), and populates post_content_filtered with plaintext.
	 *
	 * @param array<int,array<string,mixed>> $value   PT blocks from REST request.
	 * @param \WP_Post                       $post    Post object.
	 * @return true|\WP_Error
	 */
	public function update_portable_text( array $value, \WP_Post $post ) {
		// Validate: must be a sequential array with _type on first element.
		if ( ! empty( $value ) ) {
			if ( ! array_is_list( $value ) ) {
				return new \WP_Error(
					'invalid_portable_text',
					'Portable Text must be a sequential array of blocks.',
					[ 'status' => 400 ]
				);
			}

			if ( ! isset( $value[ 0 ][ '_type' ] ) ) {
				return new \WP_Error(
					'invalid_portable_text',
					'Each block must have a _type property.',
					[ 'status' => 400 ]
				);
			}
		}

		$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			return new \WP_Error(
				'invalid_portable_text',
				'Could not encode Portable Text as JSON.',
				[ 'status' => 400 ]
			);
		}

		// Write directly to DB, bypassing kses which would corrupt JSON.
		global $wpdb;

		// Extract plaintext for search.
		$plaintext = $this->extract_plaintext_from_blocks( $value );

		$wpdb->update(
			$wpdb->posts,
			[
				'post_content'          => $json,
				'post_content_filtered' => $plaintext,
			],
			[ 'ID' => $post->ID ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		clean_post_cache( $post->ID );

		return true;
	}

	/**
	 * Extract plaintext from PT blocks (for post_content_filtered).
	 *
	 * @param array<int,array<string,mixed>> $blocks PT blocks.
	 * @return string
	 */
	private function extract_plaintext_from_blocks( array $blocks ): string {
		$parts = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block[ 'children' ] ) ) {
				continue;
			}

			foreach ( $block[ 'children' ] as $child ) {
				if ( ! empty( $child[ 'text' ] ) ) {
					$parts[] = $child[ 'text' ];
				}
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Convert an array of Portable Text blocks to HTML.
	 *
	 * @param array<int,array<string,mixed>> $blocks PT blocks.
	 * @return string
	 */
	public function blocks_to_html( array $blocks ): string {
		return $this->blocks_to_format( $blocks, new Html_Serializer() );
	}

	/**
	 * Convert PT blocks to Markdown.
	 *
	 * @param array<int,array<string,mixed>> $blocks PT blocks.
	 * @return string
	 */
	public function blocks_to_markdown( array $blocks ): string {
		return $this->blocks_to_format( $blocks, new Markdown_Serializer() );
	}

	// ---- Shared Portable Text walker ----

	/**
	 * Walk PT blocks and delegate leaf rendering to a serializer.
	 *
	 * @param array<int,array<string,mixed>> $blocks     PT blocks.
	 * @param Serializer                     $serializer Output strategy.
	 * @return string
	 */
	private function blocks_to_format( array $blocks, Serializer $serializer ): string {
		$grouped = $this->group_list_items( $blocks );
		$parts   = [];

		foreach ( $grouped as $item ) {
			if ( isset( $item[ '_listGroup' ] ) ) {
				$items_content = [];
				foreach ( $item[ '_listGroup' ] as $list_item ) {
					$items_content[] = $this->walk_children( $list_item, $serializer );
				}
				$parts[] = $serializer->render_list( $items_content, $item[ 'listItem' ] );
			} else {
				$rendered = $this->walk_block( $item, $serializer );
				if ( '' !== $rendered ) {
					$parts[] = $rendered;
				}
			}
		}

		return $serializer->join_blocks( $parts );
	}

	/**
	 * Walk a single block, dispatching to the serializer by _type.
	 *
	 * @param array<string,mixed> $block      PT block.
	 * @param Serializer          $serializer Output strategy.
	 * @return string
	 */
	private function walk_block( array $block, Serializer $serializer ): string {
		$type = $block[ '_type' ] ?? '';

		return match ( $type ) {
			'block'     => $this->walk_text_block( $block, $serializer ),
			'break'     => $serializer->render_break(),
			'image'     => $serializer->render_image( $block ),
			'codeBlock' => $serializer->render_code_block( $block ),
			'embed'     => $serializer->render_embed( $block ),
			'table'     => $serializer->render_table( $block ),
			default     => $serializer->render_unknown_block( $block ),
		};
	}

	/**
	 * Walk a text block: render children, then wrap via serializer.
	 *
	 * @param array<string,mixed> $block      PT text block.
	 * @param Serializer          $serializer Output strategy.
	 * @return string
	 */
	private function walk_text_block( array $block, Serializer $serializer ): string {
		$content = $this->walk_children( $block, $serializer );

		if ( '' === trim( $content ) ) {
			return '';
		}

		return $serializer->render_text_block( $content, $block[ 'style' ] ?? 'normal' );
	}

	/**
	 * Walk child spans, applying marks via the serializer.
	 *
	 * @param array<string,mixed> $block      PT block with children/markDefs.
	 * @param Serializer          $serializer Output strategy.
	 * @return string
	 */
	private function walk_children( array $block, Serializer $serializer ): string {
		$children  = $block[ 'children' ] ?? [];
		$mark_defs = $block[ 'markDefs' ] ?? [];

		$mark_defs_map = [];
		foreach ( $mark_defs as $def ) {
			if ( isset( $def[ '_key' ] ) ) {
				$mark_defs_map[ $def[ '_key' ] ] = $def;
			}
		}

		$result = '';
		foreach ( $children as $child ) {
			$child_type = $child[ '_type' ] ?? 'span';

			if ( 'span' === $child_type ) {
				$text  = $serializer->escape_text( $child[ 'text' ] ?? '' );
				$marks = $child[ 'marks' ] ?? [];

				foreach ( $marks as $mark ) {
					if ( isset( $mark_defs_map[ $mark ] ) ) {
						$text = $serializer->wrap_annotation( $text, $mark_defs_map[ $mark ] );
					} else {
						$text = $serializer->wrap_decorator( $text, $mark );
					}
				}

				$result .= $text;
			} else {
				$result .= $serializer->render_inline_object( $child );
			}
		}

		return $result;
	}

	/**
	 * Group consecutive list items into list groups.
	 *
	 * @param array<int,array<string,mixed>> $blocks PT blocks.
	 * @return array<int,array<string,mixed>>
	 */
	private function group_list_items( array $blocks ): array {
		$result       = [];
		$current_list = null;

		foreach ( $blocks as $block ) {
			$list_item = $block[ 'listItem' ] ?? null;

			if ( $list_item ) {
				if ( null === $current_list || $current_list[ 'listItem' ] !== $list_item ) {
					if ( null !== $current_list ) {
						$result[] = $current_list;
					}
					$current_list = [
						'_listGroup' => [],
						'listItem'   => $list_item,
					];
				}
				$current_list[ '_listGroup' ][] = $block;
			} else {
				if ( null !== $current_list ) {
					$result[]     = $current_list;
					$current_list = null;
				}
				$result[] = $block;
			}
		}

		if ( null !== $current_list ) {
			$result[] = $current_list;
		}

		return $result;
	}

	// ---- Markdown alternate representation ----

	/**
	 * Output <link rel="alternate" type="text/markdown"> in wp_head.
	 *
	 * Works on singular posts, home page, and archive pages.
	 */
	public function render_markdown_link(): void {
		if ( is_singular() ) {
			$post = get_post();
			if ( ! $post || ! $this->is_portable_text_content( $post->post_content ) ) {
				return;
			}
			$url   = add_query_arg( 'format', 'markdown', get_permalink( $post ) );
			$title = get_the_title( $post );
		} elseif ( is_home() || is_archive() ) {
			$url   = add_query_arg( 'format', 'markdown' );
			$title = is_home() ? get_bloginfo( 'name' ) : get_the_archive_title();
		} else {
			return;
		}

		printf(
			'<link rel="alternate" type="text/markdown" href="%s" title="%s (Markdown)" />' . "\n",
			esc_url( $url ),
			esc_attr( $title )
		);
	}

	/**
	 * Serve markdown when ?format=markdown or Accept: text/markdown.
	 *
	 * Handles singular posts, home page, and archive pages.
	 */
	public function serve_markdown(): void {
		if ( ! is_singular() && ! is_home() && ! is_archive() ) {
			return;
		}

		$wants_markdown = false;

		// Check query parameter.
		$format = get_query_var( 'format' );
		if ( '' === $format ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$format = sanitize_text_field( wp_unslash( $_GET[ 'format' ] ?? '' ) );
		}
		if ( 'markdown' === $format ) {
			$wants_markdown = true;
		}

		// Check Accept header for content negotiation.
		if ( ! $wants_markdown ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$accept = wp_unslash( $_SERVER[ 'HTTP_ACCEPT' ] ?? '' );
			if ( str_contains( $accept, 'text/markdown' ) ) {
				$wants_markdown = true;
			}
		}

		if ( ! $wants_markdown ) {
			return;
		}

		if ( is_singular() ) {
			$markdown = $this->markdown_for_singular();
		} else {
			$markdown = $this->markdown_for_archive();
		}

		if ( '' === $markdown ) {
			return;
		}

		// Prevent caching proxies from mixing HTML and Markdown responses.
		header( 'Vary: Accept' );
		header( 'Content-Type: text/markdown; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw markdown output.
		echo $markdown;
		exit;
	}

	/**
	 * Build markdown for a singular post.
	 *
	 * @return string Markdown or empty string.
	 */
	private function markdown_for_singular(): string {
		$post = get_post();
		if ( ! $post || ! $this->is_portable_text_content( $post->post_content ) ) {
			return '';
		}

		$decoded = json_decode( $post->post_content, true );
		$title   = get_the_title( $post );

		return "# {$title}\n\n" . $this->blocks_to_markdown( $decoded );
	}

	/**
	 * Build markdown for home/archive pages — concatenates all posts in the loop.
	 *
	 * @return string Markdown or empty string.
	 */
	private function markdown_for_archive(): string {
		global $wp_query;

		if ( empty( $wp_query->posts ) ) {
			return '';
		}

		// Archive heading.
		if ( is_home() ) {
			$heading = get_bloginfo( 'name' );
		} else {
			$heading = get_the_archive_title();
		}

		$parts = [ "# {$heading}\n" ];

		foreach ( $wp_query->posts as $post ) {
			if ( ! $this->is_portable_text_content( $post->post_content ) ) {
				continue;
			}

			$decoded = json_decode( $post->post_content, true );
			$title   = get_the_title( $post );
			$link    = get_permalink( $post );
			$parts[] = "## [{$title}]({$link})\n";
			$parts[] = $this->blocks_to_markdown( $decoded );
		}

		return implode( "\n", $parts );
	}

	/**
	 * Check if a string looks like Portable Text JSON.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	private function is_portable_text_content( string $content ): bool {
		if ( '' === $content ) {
			return false;
		}
		$decoded = json_decode( $content, true );
		return is_array( $decoded ) && ! empty( $decoded ) && isset( $decoded[ 0 ][ '_type' ] );
	}
}
