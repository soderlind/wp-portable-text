<?php
/**
 * Frontend renderer — converts PT JSON stored in post_content to HTML.
 *
 * Lightweight PHP renderer for Portable Text. Does not depend on sanity-php.
 * The PT spec is simple enough to walk directly.
 *
 * @package WPPortableText
 */

declare( strict_types=1 );

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
		if ( ! isset( $decoded[0]['_type'] ) ) {
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

		if ( is_array( $decoded ) && ! empty( $decoded ) && isset( $decoded[0]['_type'] ) ) {
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
		if ( ! is_array( $decoded ) || ! isset( $decoded[0]['_type'] ) ) {
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
					'get_callback' => static function ( array $object ): ?array {
						$post = get_post( $object['id'] ?? 0 );
						if ( ! $post || ! is_string( $post->post_content ) || '' === $post->post_content ) {
							return null;
						}

						$decoded = json_decode( $post->post_content, true );

						if ( is_array( $decoded ) && isset( $decoded[0]['_type'] ) ) {
							return $decoded;
						}

						return null;
					},
					'schema'       => [
						'description' => 'Portable Text JSON representation of the content.',
						'type'        => [ 'array', 'null' ],
					],
				]
			);
		}
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
			if ( isset( $item['_listGroup'] ) ) {
				$html .= $this->render_list( $item['_listGroup'], $item['listItem'] );
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
			$list_item = $block['listItem'] ?? null;

			if ( $list_item ) {
				if ( null === $current_list || $current_list['listItem'] !== $list_item ) {
					if ( null !== $current_list ) {
						$result[] = $current_list;
					}
					$current_list = [
						'_listGroup' => [],
						'listItem'   => $list_item,
					];
				}
				$current_list['_listGroup'][] = $block;
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
		$type = $block['_type'] ?? '';

		switch ( $type ) {
			case 'block':
				return $this->render_text_block( $block );

			case 'break':
				return "<hr />\n";

			case 'image':
				return $this->render_image_block( $block );

			case 'codeBlock':
				return $this->render_code_block( $block );

			case 'embed':
				return $this->render_embed_block( $block );

			case 'table':
				return $this->render_table_block( $block );

			default:
				/**
				 * Filters the HTML output for a custom PT block type.
				 *
				 * @param string              $html  Default empty string.
				 * @param array<string,mixed> $block The PT block data.
				 */
				return (string) apply_filters( 'wp_portable_text_render_block', '', $block );
		}
	}

	/**
	 * Render a text block (paragraph, heading, blockquote).
	 *
	 * @param array<string,mixed> $block PT text block.
	 * @return string
	 */
	private function render_text_block( array $block ): string {
		$style   = $block['style'] ?? 'normal';
		$content = $this->render_children( $block );

		if ( '' === trim( $content ) ) {
			return '';
		}

		switch ( $style ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				return "<{$style}>{$content}</{$style}>\n";

			case 'blockquote':
				return "<blockquote><p>{$content}</p></blockquote>\n";

			case 'normal':
			default:
				return "<p>{$content}</p>\n";
		}
	}

	/**
	 * Render children (spans) of a text block, applying marks.
	 *
	 * @param array<string,mixed> $block PT text block.
	 * @return string
	 */
	private function render_children( array $block ): string {
		$children = $block['children'] ?? [];
		$mark_defs = $block['markDefs'] ?? [];

		// Index markDefs by _key.
		$mark_defs_map = [];
		foreach ( $mark_defs as $def ) {
			if ( isset( $def['_key'] ) ) {
				$mark_defs_map[ $def['_key'] ] = $def;
			}
		}

		$html = '';
		foreach ( $children as $child ) {
			$child_type = $child['_type'] ?? 'span';

			if ( 'span' === $child_type ) {
				$text  = esc_html( $child['text'] ?? '' );
				$marks = $child['marks'] ?? [];

				// Apply marks (decorators and annotations).
				foreach ( $marks as $mark ) {
					if ( isset( $mark_defs_map[ $mark ] ) ) {
						$def = $mark_defs_map[ $mark ];
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
		switch ( $decorator ) {
			case 'strong':
				return "<strong>{$text}</strong>";
			case 'em':
				return "<em>{$text}</em>";
			case 'underline':
				return "<u>{$text}</u>";
			case 'strike-through':
			case 'strike':
				return "<s>{$text}</s>";
			case 'code':
				return "<code>{$text}</code>";
			case 'subscript':
				return "<sub>{$text}</sub>";
			case 'superscript':
				return "<sup>{$text}</sup>";
			default:
				return $text;
		}
	}

	/**
	 * Apply an annotation (link, etc.) to text.
	 *
	 * @param string              $text HTML text.
	 * @param array<string,mixed> $def  Mark definition.
	 * @return string
	 */
	private function apply_annotation( string $text, array $def ): string {
		$type = $def['_type'] ?? '';

		switch ( $type ) {
			case 'link':
				$href = $def['href'] ?? '';
				if ( ! $this->uri_looks_safe( $href ) ) {
					return $text;
				}
				$href = esc_url( $href );
				$rel  = '';
				if ( 0 !== strpos( $href, '/' ) && 0 !== strpos( $href, home_url() ) ) {
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
			if ( 0 === strpos( $lower, $scheme ) ) {
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
		$src     = esc_url( $block['src'] ?? $block['url'] ?? '' );
		$alt     = esc_attr( $block['alt'] ?? '' );
		$caption = $block['caption'] ?? '';

		if ( '' === $src ) {
			return '';
		}

		$html = '<figure class="wp-portable-text-image">';
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
		$code     = esc_html( $block['code'] ?? '' );
		$language = esc_attr( $block['language'] ?? '' );

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
		$url = $block['url'] ?? '';

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
		$rows = $block['rows'] ?? [];
		if ( empty( $rows ) ) {
			return '';
		}

		$html = "<table class=\"wp-portable-text-table\">\n";
		foreach ( $rows as $i => $row ) {
			$cells = $row['cells'] ?? [];
			$tag   = ( 0 === $i && ! empty( $block['hasHeaderRow'] ) ) ? 'th' : 'td';

			if ( 0 === $i && ! empty( $block['hasHeaderRow'] ) ) {
				$html .= "<thead>\n";
			} elseif ( 1 === $i && ! empty( $block['hasHeaderRow'] ) ) {
				$html .= "<tbody>\n";
			}

			$html .= '<tr>';
			foreach ( $cells as $cell ) {
				$html .= "<{$tag}>" . esc_html( (string) $cell ) . "</{$tag}>";
			}
			$html .= "</tr>\n";

			if ( 0 === $i && ! empty( $block['hasHeaderRow'] ) ) {
				$html .= "</thead>\n";
			}
		}

		if ( ! empty( $block['hasHeaderRow'] ) && count( $rows ) > 1 ) {
			$html .= "</tbody>\n";
		}

		$html .= "</table>\n";
		return $html;
	}
}
