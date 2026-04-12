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
		// Pre-process: group list items into lists.
		$grouped = $this->group_list_items( $blocks );
		$html    = '';

		foreach ( $grouped as $item ) {
			if ( isset( $item[ '_listGroup' ] ) ) {
				$html .= $this->render_list( $item[ '_listGroup' ], $item[ 'listItem' ] );
			} else {
				$html .= $this->render_block( $item );
			}
		}

		return $html;
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

	/**
	 * Render a list group.
	 *
	 * @param array<int,array<string,mixed>> $items    List item blocks.
	 * @param string                         $list_type bullet|number.
	 * @return string
	 */
	private function render_list( array $items, string $list_type ): string {
		$tag  = 'number' === $list_type ? 'ol' : 'ul';
		$html = "<{$tag}>\n";

		foreach ( $items as $item ) {
			$html .= '<li>' . $this->render_children( $item ) . "</li>\n";
		}

		$html .= "</{$tag}>\n";
		return $html;
	}

	/**
	 * Render a single block to HTML.
	 *
	 * @param array<string,mixed> $block PT block.
	 * @return string
	 */
	private function render_block( array $block ): string {
		$type = $block[ '_type' ] ?? '';

		return match ( $type ) {
			'block'     => $this->render_text_block( $block ),
			'break'     => "<hr />\n",
			'image'     => $this->render_image_block( $block ),
			'codeBlock' => $this->render_code_block( $block ),
			'embed'     => $this->render_embed_block( $block ),
			'table'     => $this->render_table_block( $block ),
			default     => (string) apply_filters( 'wp_portable_text_render_block', '', $block ),
		};
	}

	/**
	 * Render a text block (paragraph, heading, blockquote).
	 *
	 * @param array<string,mixed> $block PT text block.
	 * @return string
	 */
	private function render_text_block( array $block ): string {
		$style   = $block[ 'style' ] ?? 'normal';
		$content = $this->render_children( $block );

		if ( '' === trim( $content ) ) {
			return '';
		}

		return match ( $style ) {
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => "<{$style}>{$content}</{$style}>\n",
			'blockquote'                       => "<blockquote><p>{$content}</p></blockquote>\n",
			default                            => "<p>{$content}</p>\n",
		};
	}

	/**
	 * Render children (spans) of a text block, applying marks.
	 *
	 * @param array<string,mixed> $block PT text block.
	 * @return string
	 */
	private function render_children( array $block ): string {
		$children  = $block[ 'children' ] ?? [];
		$mark_defs = $block[ 'markDefs' ] ?? [];

		// Index markDefs by _key.
		$mark_defs_map = [];
		foreach ( $mark_defs as $def ) {
			if ( isset( $def[ '_key' ] ) ) {
				$mark_defs_map[ $def[ '_key' ] ] = $def;
			}
		}

		$html = '';
		foreach ( $children as $child ) {
			$child_type = $child[ '_type' ] ?? 'span';

			if ( 'span' === $child_type ) {
				$text  = esc_html( $child[ 'text' ] ?? '' );
				$marks = $child[ 'marks' ] ?? [];

				// Apply marks (decorators and annotations).
				foreach ( $marks as $mark ) {
					if ( isset( $mark_defs_map[ $mark ] ) ) {
						$def  = $mark_defs_map[ $mark ];
						$text = $this->apply_annotation( $text, $def );
					} else {
						$text = $this->apply_decorator( $text, $mark );
					}
				}

				$html .= $text;
			} else {
				// Inline object.
				$html .= (string) apply_filters( 'wp_portable_text_render_inline', '', $child );
			}
		}

		return $html;
	}

	/**
	 * Apply a decorator (bold, italic, etc.) to text.
	 *
	 * @param string $text      HTML text.
	 * @param string $decorator Decorator name.
	 * @return string
	 */
	private function apply_decorator( string $text, string $decorator ): string {
		return match ( $decorator ) {
			'strong'                   => "<strong>{$text}</strong>",
			'em'                       => "<em>{$text}</em>",
			'underline'                => "<u>{$text}</u>",
			'strike-through', 'strike' => "<s>{$text}</s>",
			'code'                     => "<code>{$text}</code>",
			'subscript'                => "<sub>{$text}</sub>",
			'superscript'              => "<sup>{$text}</sup>",
			default                    => $text,
		};
	}

	/**
	 * Apply an annotation (link, etc.) to text.
	 *
	 * @param string              $text HTML text.
	 * @param array<string,mixed> $def  Mark definition.
	 * @return string
	 */
	private function apply_annotation( string $text, array $def ): string {
		$type = $def[ '_type' ] ?? '';

		switch ( $type ) {
			case 'link':
				$href = $def[ 'href' ] ?? '';
				if ( ! $this->uri_looks_safe( $href ) ) {
					return $text;
				}
				$href = esc_url( $href );
				$rel  = '';
				if ( ! str_starts_with( $href, '/' ) && ! str_starts_with( $href, home_url() ) ) {
					$rel = ' rel="noopener noreferrer"';
				}
				return "<a href=\"{$href}\"{$rel}>{$text}</a>";

			default:
				/**
				 * Filters the HTML output for a custom annotation type.
				 *
				 * @param string              $text HTML text.
				 * @param array<string,mixed> $def  Mark definition.
				 */
				return (string) apply_filters( 'wp_portable_text_render_annotation', $text, $def );
		}
	}

	/**
	 * Check if a URI is safe to render as href.
	 *
	 * Rejects javascript:, data:, vbscript: schemes.
	 *
	 * @param string $uri URI string.
	 * @return bool
	 */
	private function uri_looks_safe( string $uri ): bool {
		$uri = trim( $uri );
		if ( '' === $uri ) {
			return false;
		}

		$dangerous = [ 'javascript:', 'data:', 'vbscript:' ];
		$lower     = strtolower( $uri );

		foreach ( $dangerous as $scheme ) {
			if ( str_starts_with( $lower, $scheme ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Render an image block.
	 *
	 * @param array<string,mixed> $block PT image block.
	 * @return string
	 */
	private function render_image_block( array $block ): string {
		$src     = esc_url( $block[ 'src' ] ?? $block[ 'url' ] ?? '' );
		$alt     = esc_attr( $block[ 'alt' ] ?? '' );
		$caption = $block[ 'caption' ] ?? '';

		if ( '' === $src ) {
			return '';
		}

		$html  = '<figure class="wp-portable-text-image">';
		$html .= "<img src=\"{$src}\" alt=\"{$alt}\" />";
		if ( '' !== $caption ) {
			$html .= '<figcaption>' . esc_html( $caption ) . '</figcaption>';
		}
		$html .= "</figure>\n";

		return $html;
	}

	/**
	 * Render a code block.
	 *
	 * @param array<string,mixed> $block PT code block.
	 * @return string
	 */
	private function render_code_block( array $block ): string {
		$code     = esc_html( $block[ 'code' ] ?? '' );
		$language = esc_attr( $block[ 'language' ] ?? '' );

		$lang_attr = $language ? " class=\"language-{$language}\"" : '';
		return "<pre><code{$lang_attr}>{$code}</code></pre>\n";
	}

	/**
	 * Render an embed block.
	 *
	 * @param array<string,mixed> $block PT embed block.
	 * @return string
	 */
	private function render_embed_block( array $block ): string {
		$url = $block[ 'url' ] ?? '';

		if ( '' === $url || ! $this->uri_looks_safe( $url ) ) {
			return '';
		}

		// Use WP oEmbed for rendering.
		global $wp_embed;
		if ( $wp_embed ) {
			$html = $wp_embed->shortcode( [], $url );
			if ( $html ) {
				return $html;
			}
		}

		return '<p><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . "</a></p>\n";
	}

	/**
	 * Render a table block.
	 *
	 * @param array<string,mixed> $block PT table block.
	 * @return string
	 */
	private function render_table_block( array $block ): string {
		$rows = $block[ 'rows' ] ?? [];
		if ( empty( $rows ) ) {
			return '';
		}

		$html = "<table class=\"wp-portable-text-table\">\n";
		foreach ( $rows as $i => $row ) {
			$cells = $row[ 'cells' ] ?? [];
			$tag   = ( 0 === $i && ! empty( $block[ 'hasHeaderRow' ] ) ) ? 'th' : 'td';

			if ( 0 === $i && ! empty( $block[ 'hasHeaderRow' ] ) ) {
				$html .= "<thead>\n";
			} elseif ( 1 === $i && ! empty( $block[ 'hasHeaderRow' ] ) ) {
				$html .= "<tbody>\n";
			}

			$html .= '<tr>';
			foreach ( $cells as $cell ) {
				$html .= "<{$tag}>" . esc_html( (string) $cell ) . "</{$tag}>";
			}
			$html .= "</tr>\n";

			if ( 0 === $i && ! empty( $block[ 'hasHeaderRow' ] ) ) {
				$html .= "</thead>\n";
			}
		}

		if ( ! empty( $block[ 'hasHeaderRow' ] ) && count( $rows ) > 1 ) {
			$html .= "</tbody>\n";
		}

		$html .= "</table>\n";
		return $html;
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

	/**
	 * Convert PT blocks to Markdown.
	 *
	 * @param array<int,array<string,mixed>> $blocks PT blocks.
	 * @return string
	 */
	public function blocks_to_markdown( array $blocks ): string {
		$parts = [];
		$i     = 0;
		$count = count( $blocks );

		while ( $i < $count ) {
			$block = $blocks[ $i ];

			if ( ! empty( $block[ 'listItem' ] ) ) {
				$list_type = $block[ 'listItem' ];
				$ordered   = 'number' === $list_type;
				$idx       = 1;

				while ( $i < $count && ( $blocks[ $i ][ 'listItem' ] ?? '' ) === $list_type ) {
					$prefix  = $ordered ? "{$idx}. " : '- ';
					$parts[] = $prefix . $this->md_render_children( $blocks[ $i ] );
					++$idx;
					++$i;
				}
				$parts[] = '';
				continue;
			}

			$parts[] = $this->md_render_block( $block );
			++$i;
		}

		return implode( "\n", $parts );
	}

	/**
	 * Render a single block as Markdown.
	 *
	 * @param array<string,mixed> $block PT block.
	 * @return string
	 */
	private function md_render_block( array $block ): string {
		$type = $block[ '_type' ] ?? '';

		return match ( $type ) {
			'block'     => $this->md_render_text_block( $block ),
			'break'     => "---\n",
			'image'     => $this->md_render_image( $block ),
			'codeBlock' => $this->md_render_code_block( $block ),
			'embed'     => ( $block[ 'url' ] ?? '' ) . "\n",
			'table'     => $this->md_render_table( $block ),
			default     => '',
		};
	}

	/**
	 * Render a text block as Markdown.
	 *
	 * @param array<string,mixed> $block PT text block.
	 * @return string
	 */
	private function md_render_text_block( array $block ): string {
		$content = $this->md_render_children( $block );
		if ( '' === trim( $content ) ) {
			return '';
		}

		$style = $block[ 'style' ] ?? 'normal';

		return match ( $style ) {
			'h1'         => "# {$content}\n",
			'h2'         => "## {$content}\n",
			'h3'         => "### {$content}\n",
			'h4'         => "#### {$content}\n",
			'h5'         => "##### {$content}\n",
			'h6'         => "###### {$content}\n",
			'blockquote' => "> {$content}\n",
			default      => "{$content}\n",
		};
	}

	/**
	 * Render children spans as Markdown text.
	 *
	 * @param array<string,mixed> $block PT block with children/markDefs.
	 * @return string
	 */
	private function md_render_children( array $block ): string {
		$children  = $block[ 'children' ] ?? [];
		$mark_defs = $block[ 'markDefs' ] ?? [];

		$mark_defs_map = [];
		foreach ( $mark_defs as $def ) {
			if ( isset( $def[ '_key' ] ) ) {
				$mark_defs_map[ $def[ '_key' ] ] = $def;
			}
		}

		$md = '';
		foreach ( $children as $child ) {
			if ( 'span' !== ( $child[ '_type' ] ?? '' ) ) {
				continue;
			}

			$text  = $child[ 'text' ] ?? '';
			$marks = $child[ 'marks' ] ?? [];

			foreach ( $marks as $mark ) {
				if ( isset( $mark_defs_map[ $mark ] ) ) {
					$text = $this->md_apply_annotation( $text, $mark_defs_map[ $mark ] );
				} else {
					$text = $this->md_apply_decorator( $text, $mark );
				}
			}

			$md .= $text;
		}

		return $md;
	}

	/**
	 * Apply a Markdown decorator.
	 *
	 * @param string $text      Text.
	 * @param string $decorator Decorator name.
	 * @return string
	 */
	private function md_apply_decorator( string $text, string $decorator ): string {
		return match ( $decorator ) {
			'strong'                   => "**{$text}**",
			'em'                       => "*{$text}*",
			'underline'                => "<u>{$text}</u>",
			'strike-through', 'strike' => "~~{$text}~~",
			'code'                     => "`{$text}`",
			'subscript'                => "<sub>{$text}</sub>",
			'superscript'              => "<sup>{$text}</sup>",
			default                    => $text,
		};
	}

	/**
	 * Apply a Markdown annotation.
	 *
	 * @param string              $text Text.
	 * @param array<string,mixed> $def  Mark definition.
	 * @return string
	 */
	private function md_apply_annotation( string $text, array $def ): string {
		if ( 'link' === ( $def[ '_type' ] ?? '' ) && ! empty( $def[ 'href' ] ) ) {
			$href = $def[ 'href' ];
			return "[{$text}]({$href})";
		}
		return $text;
	}

	/**
	 * Render an image block as Markdown.
	 *
	 * @param array<string,mixed> $block PT image block.
	 * @return string
	 */
	private function md_render_image( array $block ): string {
		$alt = $block[ 'alt' ] ?? '';
		$src = $block[ 'src' ] ?? $block[ 'url' ] ?? '';
		$md  = "![{$alt}]({$src})";

		if ( ! empty( $block[ 'caption' ] ) ) {
			$md .= "\n\n*{$block[ 'caption' ]}*";
		}

		return $md . "\n";
	}

	/**
	 * Render a code block as Markdown.
	 *
	 * @param array<string,mixed> $block PT code block.
	 * @return string
	 */
	private function md_render_code_block( array $block ): string {
		$lang = $block[ 'language' ] ?? '';
		$code = $block[ 'code' ] ?? '';

		return "```{$lang}\n{$code}\n```\n";
	}

	/**
	 * Render a table block as Markdown.
	 *
	 * @param array<string,mixed> $block PT table block.
	 * @return string
	 */
	private function md_render_table( array $block ): string {
		$rows = $block[ 'rows' ] ?? [];
		if ( empty( $rows ) ) {
			return '';
		}

		$lines      = [];
		$has_header = ! empty( $block[ 'hasHeaderRow' ] );
		$first_row  = $rows[ 0 ][ 'cells' ] ?? [];
		$col_count  = count( $first_row );

		foreach ( $rows as $i => $row ) {
			$cells   = $row[ 'cells' ] ?? [];
			$lines[] = '| ' . implode( ' | ', array_map( 'strval', $cells ) ) . ' |';

			// Add separator after header row.
			if ( 0 === $i && $has_header ) {
				$lines[] = '| ' . implode( ' | ', array_fill( 0, $col_count, '---' ) ) . ' |';
			}
		}

		// If no header row, add separator after first row as markdown requires it.
		if ( ! $has_header && $col_count > 0 ) {
			array_splice( $lines, 1, 0, '| ' . implode( ' | ', array_fill( 0, $col_count, '---' ) ) . ' |' );
		}

		return implode( "\n", $lines ) . "\n";
	}
}
