<?php
/**
 * Serializer interface — pluggable output strategy for the PT walker.
 *
 * @package WPPortableText\Serializers
 */

declare(strict_types=1);

namespace WPPortableText\Serializers;

/**
 * Format-specific output callbacks for the shared Portable Text walker.
 */
interface Serializer {

	/**
	 * Escape span text for the target format.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	public function escape_text( string $text ): string;

	/**
	 * Wrap text with a decorator (bold, italic, etc.).
	 *
	 * @param string $text      Already-escaped text.
	 * @param string $decorator Decorator name (strong, em, code, …).
	 * @return string
	 */
	public function wrap_decorator( string $text, string $decorator ): string;

	/**
	 * Wrap text with an annotation (link, etc.).
	 *
	 * @param string              $text Already-escaped text.
	 * @param array<string,mixed> $def  Mark definition from markDefs.
	 * @return string
	 */
	public function wrap_annotation( string $text, array $def ): string;

	/**
	 * Render an inline object (non-span child).
	 *
	 * @param array<string,mixed> $child Inline object.
	 * @return string
	 */
	public function render_inline_object( array $child ): string;

	/**
	 * Wrap rendered children content in a text block (paragraph, heading, blockquote).
	 *
	 * @param string $content Rendered children text.
	 * @param string $style   Block style (normal, h1–h6, blockquote).
	 * @return string
	 */
	public function render_text_block( string $content, string $style ): string;

	/**
	 * Render a horizontal rule / break block.
	 *
	 * @return string
	 */
	public function render_break(): string;

	/**
	 * Render an image block.
	 *
	 * @param array<string,mixed> $block PT image block.
	 * @return string
	 */
	public function render_image( array $block ): string;

	/**
	 * Render a fenced code block.
	 *
	 * @param array<string,mixed> $block PT code block.
	 * @return string
	 */
	public function render_code_block( array $block ): string;

	/**
	 * Render an embed block.
	 *
	 * @param array<string,mixed> $block PT embed block.
	 * @return string
	 */
	public function render_embed( array $block ): string;

	/**
	 * Render a table block.
	 *
	 * @param array<string,mixed> $block PT table block.
	 * @return string
	 */
	public function render_table( array $block ): string;

	/**
	 * Render a list from pre-rendered item contents.
	 *
	 * @param array<int,string> $items     Rendered children text for each list item.
	 * @param string            $list_type bullet|number.
	 * @return string
	 */
	public function render_list( array $items, string $list_type ): string;

	/**
	 * Render an unknown / custom block type.
	 *
	 * @param array<string,mixed> $block PT block.
	 * @return string
	 */
	public function render_unknown_block( array $block ): string;

	/**
	 * Join rendered block parts into final output.
	 *
	 * @param array<int,string> $parts Rendered block strings.
	 * @return string
	 */
	public function join_blocks( array $parts ): string;
}
