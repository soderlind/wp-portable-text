<?php
/**
 * Markdown serializer — renders Portable Text leaf nodes as Markdown.
 *
 * @package WPPortableText\Serializers
 */

declare(strict_types=1);

namespace WPPortableText\Serializers;

/**
 * Produces Markdown output for the shared PT walker.
 */
class Markdown_Serializer implements Serializer {

	public function escape_text( string $text ): string {
		return $text;
	}

	public function wrap_decorator( string $text, string $decorator ): string {
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

	public function wrap_annotation( string $text, array $def ): string {
		if ( 'link' === ( $def[ '_type' ] ?? '' ) && ! empty( $def[ 'href' ] ) ) {
			$href = $def[ 'href' ];
			return "[{$text}]({$href})";
		}
		return $text;
	}

	public function render_inline_object( array $child ): string {
		return '';
	}

	public function render_text_block( string $content, string $style ): string {
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

	public function render_break(): string {
		return "---\n";
	}

	public function render_image( array $block ): string {
		$alt = $block[ 'alt' ] ?? '';
		$src = $block[ 'src' ] ?? $block[ 'url' ] ?? '';
		$md  = "![{$alt}]({$src})";

		if ( ! empty( $block[ 'caption' ] ) ) {
			$md .= "\n\n*{$block[ 'caption' ]}*";
		}

		return $md . "\n";
	}

	public function render_code_block( array $block ): string {
		$lang = $block[ 'language' ] ?? '';
		$code = $block[ 'code' ] ?? '';

		return "```{$lang}\n{$code}\n```\n";
	}

	public function render_embed( array $block ): string {
		return ( $block[ 'url' ] ?? '' ) . "\n";
	}

	public function render_table( array $block ): string {
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

			if ( 0 === $i && $has_header ) {
				$lines[] = '| ' . implode( ' | ', array_fill( 0, $col_count, '---' ) ) . ' |';
			}
		}

		if ( ! $has_header && $col_count > 0 ) {
			array_splice( $lines, 1, 0, '| ' . implode( ' | ', array_fill( 0, $col_count, '---' ) ) . ' |' );
		}

		return implode( "\n", $lines ) . "\n";
	}

	public function render_list( array $items, string $list_type ): string {
		$ordered = 'number' === $list_type;
		$lines   = [];

		foreach ( $items as $idx => $item ) {
			$prefix  = $ordered ? ( $idx + 1 ) . '. ' : '- ';
			$lines[] = $prefix . $item;
		}

		return implode( "\n", $lines ) . "\n";
	}

	public function render_unknown_block( array $block ): string {
		return '';
	}

	public function join_blocks( array $parts ): string {
		return implode( "\n", $parts );
	}
}
