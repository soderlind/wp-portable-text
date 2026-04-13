<?php
/**
 * HTML serializer — renders Portable Text leaf nodes as HTML.
 *
 * @package WPPortableText\Serializers
 */

declare(strict_types=1);

namespace WPPortableText\Serializers;

/**
 * Produces HTML output for the shared PT walker.
 */
class Html_Serializer implements Serializer {

	public function escape_text( string $text ): string {
		return esc_html( $text );
	}

	public function wrap_decorator( string $text, string $decorator ): string {
		return match ( $decorator ) {
			'strong'                   => "<strong>{$text}</strong>",
			'em'                       => "<em>{$text}</em>",
			'underline'                => "<u>{$text}</u>",
			'strike-through', 'strike' => "<s>{$text}</s>",
			'code'                     => "<code class=\"wp-portable-text-inline-code\">{$text}</code>",
			'subscript'                => "<sub>{$text}</sub>",
			'superscript'              => "<sup>{$text}</sup>",
			default                    => $text,
		};
	}

	public function wrap_annotation( string $text, array $def ): string {
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
				/** This filter is documented in class-renderer.php */
				return (string) apply_filters( 'wp_portable_text_render_annotation', $text, $def );
		}
	}

	public function render_inline_object( array $child ): string {
		return (string) apply_filters( 'wp_portable_text_render_inline', '', $child );
	}

	public function render_text_block( string $content, string $style ): string {
		return match ( $style ) {
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => "<{$style}>{$content}</{$style}>\n",
			'blockquote'                       => "<blockquote><p>{$content}</p></blockquote>\n",
			default                            => "<p>{$content}</p>\n",
		};
	}

	public function render_break(): string {
		return "<hr />\n";
	}

	public function render_image( array $block ): string {
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

	public function render_code_block( array $block ): string {
		$code     = $this->normalize_code_content( (string) ( $block[ 'code' ] ?? '' ) );
		$code     = esc_html( $code );
		$language = esc_attr( $block[ 'language' ] ?? '' );

		$code_classes   = [ 'wp-portable-text-code' ];
		$language_badge = '';

		if ( '' !== $language ) {
			$code_classes[] = 'language-' . $language;
			$language_badge = "<span class=\"wp-portable-text-code-language\">{$language}</span>";
		}

		$class_attr = ' class="' . implode( ' ', $code_classes ) . '"';

		return "<pre class=\"wp-portable-text-code-block\">{$language_badge}<code{$class_attr}>{$code}</code></pre>\n";
	}

	public function render_embed( array $block ): string {
		$url = $block[ 'url' ] ?? '';

		if ( '' === $url || ! $this->uri_looks_safe( $url ) ) {
			return '';
		}

		global $wp_embed;
		if ( $wp_embed ) {
			$html = $wp_embed->shortcode( [], $url );
			if ( $html ) {
				return $html;
			}
		}

		return '<p><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . "</a></p>\n";
	}

	public function render_table( array $block ): string {
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

	public function render_list( array $items, string $list_type ): string {
		$tag  = 'number' === $list_type ? 'ol' : 'ul';
		$html = "<{$tag}>\n";

		foreach ( $items as $item ) {
			$html .= "<li>{$item}</li>\n";
		}

		$html .= "</{$tag}>\n";
		return $html;
	}

	public function render_unknown_block( array $block ): string {
		return (string) apply_filters( 'wp_portable_text_render_block', '', $block );
	}

	public function join_blocks( array $parts ): string {
		return implode( '', $parts );
	}

	/**
	 * Check if a URI is safe to render as href.
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
	 * Normalize stored code content for rendering.
	 *
	 * Converts literal escaped newline sequences into actual line breaks so
	 * imported content like "line one\\nline two" renders as multiline code.
	 *
	 * @param string $code Raw code content.
	 * @return string
	 */
	private function normalize_code_content( string $code ): string {
		$code = str_replace( [ "\r\n", "\r" ], "\n", $code );
		return str_replace( [ '\\r\\n', '\\n', '\\r' ], "\n", $code );
	}
}
