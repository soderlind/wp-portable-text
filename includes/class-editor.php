<?php
/**
 * Editor replacement — disables Block Editor, mounts Portable Text editor.
 *
 * @package WPPortableText
 */

declare( strict_types=1 );

namespace WPPortableText;

/**
 * Handles editor replacement in wp-admin.
 */
class Editor {

	/**
	 * Pending blocks from list conversion (lists yield multiple blocks).
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $pending_blocks = [];

	/**
	 * Register hooks.
	 */
	/**
	 * Internal post types that should not be touched by the PT editor.
	 *
	 * These rely on the block editor or have REST schemas that assume
	 * editor support. Removing editor support from them causes fatal
	 * errors in core (e.g. WP_Navigation_Fallback).
	 */
	private const array EXCLUDED_POST_TYPES = [
		'wp_navigation',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_font_face',
		'wp_font_family',
		'wp_block',
	];

	public function register(): void {
		// Disable block editor for supported post types only.
		add_filter( 'use_block_editor_for_post', [ $this, 'disable_block_editor' ], 100, 2 );

		// Remove TinyMCE editor and inject Portable Text editor.
		add_action( 'admin_init', [ $this, 'remove_classic_editor' ] );
		add_action( 'edit_form_after_title', [ $this, 'render_editor' ] );

		// Enqueue editor assets only on post edit screens.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Tidy up meta boxes: remove Custom Fields, move Slug and Author to sidebar.
		add_action( 'add_meta_boxes', [ $this, 'adjust_meta_boxes' ] );
	}

	/**
	 * Disable block editor for post types we manage.
	 *
	 * @param bool     $use_block_editor Whether to use the block editor.
	 * @param \WP_Post $post             The post being edited.
	 * @return bool
	 */
	public function disable_block_editor( bool $use_block_editor, $post ): bool {
		if ( in_array( get_post_type( $post ), self::EXCLUDED_POST_TYPES, true ) ) {
			return $use_block_editor;
		}
		return false;
	}

	/**
	 * Remove the default post content editor (TinyMCE).
	 *
	 * We remove 'editor' support and re-add our own content area via
	 * edit_form_after_title so the classic form still loads meta boxes,
	 * title field, publish box, etc.
	 */
	public function remove_classic_editor(): void {
		$post_types = get_post_types( [ 'show_ui' => true ] );
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type, self::EXCLUDED_POST_TYPES, true ) ) {
				continue;
			}
			if ( post_type_supports( $post_type, 'editor' ) ) {
				remove_post_type_support( $post_type, 'editor' );
			}
		}
	}

	/**
	 * Remove Custom Fields metabox and move Slug/Author to the sidebar.
	 *
	 * PT stores structured JSON in post_content, so Custom Fields are not
	 * needed. Slug and Author are more useful in the sidebar than below
	 * the editor.
	 */
	public function adjust_meta_boxes(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$post_type = $screen->post_type;

		// Remove Custom Fields (postcustom) — not needed for PT content.
		remove_meta_box( 'postcustom', $post_type, 'normal' );

		// Move Slug from normal to side.
		remove_meta_box( 'slugdiv', $post_type, 'normal' );
		add_meta_box(
			'slugdiv',
			__( 'Slug' ),
			'post_slug_meta_box',
			$post_type,
			'side',
			'default'
		);

		// Move Author from normal to side.
		if ( post_type_supports( $post_type, 'author' ) ) {
			remove_meta_box( 'authordiv', $post_type, 'normal' );
			add_meta_box(
				'authordiv',
				__( 'Author' ),
				'post_author_meta_box',
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the Portable Text editor mount point.
	 *
	 * Fires inside edit-form-advanced.php after the title field.
	 * Outputs a hidden textarea named "content" (which WP uses for post_content)
	 * and a React mount point.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_editor( \WP_Post $post ): void {
		$content = $post->post_content;

		// Resolve PT value — convert HTML if needed.
		$pt_value = $this->resolve_pt_value( $content );

		// The hidden textarea stores the PT JSON that will be submitted.
		$textarea_content = null !== $pt_value
			? wp_json_encode( $pt_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			: $content;

		$nonce = wp_create_nonce( 'wp_portable_text_save' );
		?>
		<div id="wp-portable-text-wrap" class="postarea">
			<input type="hidden" name="wp_portable_text_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
			<textarea
				id="wp-portable-text-content"
				name="content"
				class="screen-reader-text"
				aria-hidden="true"
			><?php echo esc_textarea( $textarea_content ); ?></textarea>
			<div
				id="wp-portable-text-editor"
				data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
			></div>
		</div>
		<?php
	}

	/**
	 * Enqueue editor JS/CSS on post edit screens.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		// Enqueue the WP media modal for image insertion.
		wp_enqueue_media( [ 'post' => $post->ID ] );

		$asset_file = PLUGIN_DIR . '/build/editor/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [],
				'version'      => VERSION,
			];

		wp_enqueue_script(
			'wp-portable-text-editor',
			plugin_url() . 'build/editor/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wp-portable-text-editor',
			plugin_url() . 'build/editor/index.css',
			[],
			$asset['version']
		);

		// Pass current post content to the editor.
		$content  = $post->post_content;
		$pt_value = $this->resolve_pt_value( $content );

		wp_localize_script(
			'wp-portable-text-editor',
			'wpPortableText',
			[
				'initialValue' => $pt_value,
				'postId'       => $post->ID,
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'restUrl'      => rest_url(),
			]
		);
	}

	/**
	 * Resolve post content to a Portable Text value.
	 *
	 * If content is already PT JSON, decode and return it.
	 * If content is HTML (Gutenberg or classic), convert to PT blocks.
	 * If content is empty, return null (new post).
	 *
	 * @param string $content Raw post_content.
	 * @return array<int,array<string,mixed>>|null PT blocks or null.
	 */
	private function resolve_pt_value( string $content ): ?array {
		if ( '' === trim( $content ) ) {
			return null;
		}

		// Try JSON decode first.
		$decoded = json_decode( $content, true );
		if ( is_array( $decoded ) && ! empty( $decoded ) && isset( $decoded[0]['_type'] ) ) {
			return $decoded;
		}

		// Content is HTML — convert to PT blocks.
		return $this->html_to_portable_text( $content );
	}

	/**
	 * Convert HTML content to Portable Text blocks.
	 *
	 * Handles Gutenberg comment blocks and plain HTML.
	 * This is a basic server-side conversion so existing content
	 * is visible in the editor immediately.
	 *
	 * @param string $html HTML content.
	 * @return array<int,array<string,mixed>> PT blocks.
	 */
	private function html_to_portable_text( string $html ): array {
		$blocks = [];

		// Strip Gutenberg block comments.
		$html = preg_replace( '/<!--\s*\/?wp:[^>]*-->/', '', $html );

		// Parse HTML into blocks using DOMDocument.
		$doc = new \DOMDocument( '1.0', 'UTF-8' );

		// Suppress warnings for malformed HTML; wrap in root element.
		$wrapped = '<html><body>' . mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) . '</body></html>';
		@$doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			// Fallback: single text block with stripped HTML.
			return [ $this->make_text_block( wp_strip_all_tags( $html ), 'normal' ) ];
		}

		foreach ( $body->childNodes as $node ) {
			$block = $this->dom_node_to_block( $node );
			if ( null !== $block ) {
				$blocks[] = $block;
			}
			// Flush any pending blocks (from list conversion).
			if ( ! empty( $this->pending_blocks ) ) {
				array_push( $blocks, ...$this->pending_blocks );
				$this->pending_blocks = [];
			}
		}

		// If nothing was parsed, fall back to plaintext.
		if ( empty( $blocks ) ) {
			$text = wp_strip_all_tags( $html );
			if ( '' !== trim( $text ) ) {
				$blocks[] = $this->make_text_block( $text, 'normal' );
			}
		}

		return $blocks;
	}

	/**
	 * Convert a DOM node to a PT block.
	 *
	 * @param \DOMNode $node DOM node.
	 * @return array<string,mixed>|null PT block or null to skip.
	 */
	private function dom_node_to_block( \DOMNode $node ): ?array {
		// Skip whitespace-only text nodes.
		if ( $node instanceof \DOMText ) {
			$text = trim( $node->textContent );
			if ( '' === $text ) {
				return null;
			}
			return $this->make_text_block( $text, 'normal' );
		}

		if ( ! $node instanceof \DOMElement ) {
			return null;
		}

		$tag = strtolower( $node->tagName );

		// Headings.
		if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
			return $this->make_rich_text_block( $node, 'h' . $m[1] );
		}

		// Blockquote.
		if ( 'blockquote' === $tag ) {
			return $this->make_rich_text_block( $node, 'blockquote' );
		}

		// Lists.
		if ( 'ul' === $tag || 'ol' === $tag ) {
			return $this->dom_list_to_blocks( $node, 'ul' === $tag ? 'bullet' : 'number' );
		}

		// Images.
		if ( 'img' === $tag ) {
			return $this->dom_img_to_block( $node );
		}

		// Figure (may contain img + figcaption).
		if ( 'figure' === $tag ) {
			return $this->dom_figure_to_block( $node );
		}

		// Pre/code blocks.
		if ( 'pre' === $tag ) {
			$code = $node->textContent;
			$lang = '';
			$code_el = $node->getElementsByTagName( 'code' )->item( 0 );
			if ( $code_el ) {
				$code = $code_el->textContent;
				if ( preg_match( '/language-(\w+)/', $code_el->getAttribute( 'class' ), $m ) ) {
					$lang = $m[1];
				}
			}
			return [
				'_type'    => 'codeBlock',
				'_key'     => wp_generate_uuid4(),
				'code'     => $code,
				'language' => $lang,
			];
		}

		// Paragraphs and divs — treat as normal text blocks.
		if ( in_array( $tag, [ 'p', 'div' ], true ) ) {
			return $this->make_rich_text_block( $node, 'normal' );
		}

		// Fallback: extract text content.
		$text = trim( $node->textContent );
		if ( '' !== $text ) {
			return $this->make_text_block( $text, 'normal' );
		}

		return null;
	}

	/**
	 * Create a PT text block with a single span.
	 *
	 * @param string $text  Text content.
	 * @param string $style Block style.
	 * @return array<string,mixed>
	 */
	private function make_text_block( string $text, string $style ): array {
		return [
			'_type'    => 'block',
			'_key'     => wp_generate_uuid4(),
			'style'    => $style,
			'children' => [
				[
					'_type' => 'span',
					'_key'  => wp_generate_uuid4(),
					'text'  => $text,
					'marks' => [],
				],
			],
			'markDefs' => [],
		];
	}

	/**
	 * Create a PT text block from a DOM element, preserving inline marks.
	 *
	 * Walks child nodes to detect <strong>, <em>, <a>, <code>, etc.
	 *
	 * @param \DOMElement $el    Element.
	 * @param string      $style Block style.
	 * @return array<string,mixed>
	 */
	private function make_rich_text_block( \DOMElement $el, string $style ): array {
		$children  = [];
		$mark_defs = [];

		$this->walk_inline_nodes( $el, [], $children, $mark_defs );

		// If no spans were extracted, fall back to text content.
		if ( empty( $children ) ) {
			$text = trim( $el->textContent );
			if ( '' !== $text ) {
				$children[] = [
					'_type' => 'span',
					'_key'  => wp_generate_uuid4(),
					'text'  => $text,
					'marks' => [],
				];
			}
		}

		return [
			'_type'    => 'block',
			'_key'     => wp_generate_uuid4(),
			'style'    => $style,
			'children' => $children,
			'markDefs' => $mark_defs,
		];
	}

	/**
	 * Recursively walk inline DOM nodes, collecting spans with marks.
	 *
	 * @param \DOMNode                         $node       Current node.
	 * @param array<int,string>                $marks      Active mark stack.
	 * @param array<int,array<string,mixed>>   &$children  Collected spans (by ref).
	 * @param array<int,array<string,mixed>>   &$mark_defs Collected mark defs (by ref).
	 */
	private function walk_inline_nodes( \DOMNode $node, array $marks, array &$children, array &$mark_defs ): void {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMText ) {
				$text = $child->textContent;
				if ( '' !== $text ) {
					$children[] = [
						'_type' => 'span',
						'_key'  => wp_generate_uuid4(),
						'text'  => $text,
						'marks' => array_values( $marks ),
					];
				}
				continue;
			}

			if ( ! $child instanceof \DOMElement ) {
				continue;
			}

			$tag        = strtolower( $child->tagName );
			$new_marks  = $marks;

			switch ( $tag ) {
				case 'strong':
				case 'b':
					$new_marks[] = 'strong';
					break;

				case 'em':
				case 'i':
					$new_marks[] = 'em';
					break;

				case 'u':
					$new_marks[] = 'underline';
					break;

				case 's':
				case 'del':
				case 'strike':
					$new_marks[] = 'strike-through';
					break;

				case 'code':
					$new_marks[] = 'code';
					break;

				case 'a':
					$href = $child->getAttribute( 'href' );
					if ( '' !== $href ) {
						$key         = wp_generate_uuid4();
						$mark_defs[] = [
							'_key'  => $key,
							'_type' => 'link',
							'href'  => $href,
						];
						$new_marks[] = $key;
					}
					break;
			}

			// Recurse into children.
			$this->walk_inline_nodes( $child, $new_marks, $children, $mark_defs );
		}
	}

	/**
	 * Convert list DOM nodes to PT blocks.
	 *
	 * Returns the first list item as a block (lists are flattened).
	 * Note: This returns only the first item. For full list support,
	 * the migration CLI tool should be used.
	 *
	 * @param \DOMElement $list     UL/OL element.
	 * @param string      $listItem bullet|number.
	 * @return array<string,mixed>|null First list item block.
	 */
	private function dom_list_to_blocks( \DOMElement $list, string $listItem ): ?array {
		// Collect all <li> items and return them via a wrapper.
		// PT lists are blocks with 'listItem' and 'level' properties.
		$items = [];
		foreach ( $list->getElementsByTagName( 'li' ) as $li ) {
			$block             = $this->make_rich_text_block( $li, 'normal' );
			$block['listItem'] = $listItem;
			$block['level']    = 1;
			$items[]           = $block;
		}

		// Return first item; rest handled by caller iterating body children.
		// Actually, we need to return all items. Use a special marker.
		// Since dom_node_to_block returns a single block, we store extras.
		if ( ! empty( $items ) ) {
			$this->pending_blocks = array_merge( $this->pending_blocks, array_slice( $items, 1 ) );
			return $items[0];
		}

		return null;
	}

	/**
	 * Convert an img element to a PT image block.
	 *
	 * @param \DOMElement $img IMG element.
	 * @return array<string,mixed>
	 */
	private function dom_img_to_block( \DOMElement $img ): array {
		return [
			'_type' => 'image',
			'_key'  => wp_generate_uuid4(),
			'src'   => $img->getAttribute( 'src' ),
			'alt'   => $img->getAttribute( 'alt' ),
		];
	}

	/**
	 * Convert a figure element to a PT image block.
	 *
	 * @param \DOMElement $figure Figure element.
	 * @return array<string,mixed>|null
	 */
	private function dom_figure_to_block( \DOMElement $figure ): ?array {
		$img = $figure->getElementsByTagName( 'img' )->item( 0 );
		if ( ! $img ) {
			// Fallback: treat as text.
			$text = trim( $figure->textContent );
			return '' !== $text ? $this->make_text_block( $text, 'normal' ) : null;
		}

		$block = $this->dom_img_to_block( $img );

		$caption_el = $figure->getElementsByTagName( 'figcaption' )->item( 0 );
		if ( $caption_el ) {
			$block['caption'] = trim( $caption_el->textContent );
		}

		return $block;
	}
}
