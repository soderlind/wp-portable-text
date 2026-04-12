<?php
/**
 * Tests for WPPortableText\Renderer.
 */

declare( strict_types=1 );

namespace WPPortableText\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WPPortableText\Renderer;

class RendererTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private Renderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->renderer = new Renderer();

		// Default stubs for esc_* functions used throughout rendering.
		Functions\stubs( [
			'esc_html' => static function ( string $s ): string { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); },
			'esc_url'  => static function ( string $s ): string { return $s; },
			'esc_attr' => static function ( string $s ): string { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); },
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- register ---

	public function test_register_hooks_the_content(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'the_content', [ $this->renderer, 'render' ], 5 );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'wp_trim_excerpt', [ $this->renderer, 'trim_excerpt' ], 5, 2 );

		Functions\expect( 'add_filter' )
			->once()
			->with( '_wp_post_revision_field_post_content', [ $this->renderer, 'render_revision_field' ], 10, 4 );

		Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', [ $this->renderer, 'register_rest_fields' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_head', [ $this->renderer, 'render_markdown_link' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'template_redirect', [ $this->renderer, 'serve_markdown' ] );

		$this->renderer->register();
	}

	// --- render_revision_field ---

	public function test_render_revision_field_converts_pt_json_to_html(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'r1',
				'style'    => 'normal',
				'children' => [
					[ '_type' => 'span', '_key' => 's1', 'text' => 'Revision text', 'marks' => [] ],
				],
				'markDefs' => [],
			],
		] );

		$result = $this->renderer->render_revision_field( $json, 'post_content', false, 'to' );
		$this->assertSame( "<p>Revision text</p>\n", $result );
	}

	public function test_render_revision_field_returns_html_unchanged(): void {
		$html = '<p>Already HTML</p>';
		$this->assertSame( $html, $this->renderer->render_revision_field( $html ) );
	}

	public function test_render_revision_field_handles_empty_string(): void {
		$this->assertSame( '', $this->renderer->render_revision_field( '' ) );
	}

	public function test_render_revision_field_handles_null(): void {
		$this->assertSame( '', $this->renderer->render_revision_field( null ) );
	}

	// --- render ---

	public function test_render_returns_plain_html_unchanged(): void {
		$html = '<p>Just HTML</p>';
		$this->assertSame( $html, $this->renderer->render( $html ) );
	}

	public function test_render_returns_empty_string_for_empty_array(): void {
		$this->assertSame( '[]', $this->renderer->render( '[]' ) );
	}

	public function test_render_paragraph(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'a1',
				'style'    => 'normal',
				'children' => [
					[ '_type' => 'span', '_key' => 's1', 'text' => 'Hello world', 'marks' => [] ],
				],
				'markDefs' => [],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertSame( "<p>Hello world</p>\n", $result );
	}

	public function test_render_headings(): void {
		foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $style ) {
			$json = json_encode( [
				[
					'_type'    => 'block',
					'_key'     => 'h',
					'style'    => $style,
					'children' => [
						[ '_type' => 'span', '_key' => 's', 'text' => 'Title', 'marks' => [] ],
					],
					'markDefs' => [],
				],
			] );

			$result = $this->renderer->render( $json );
			$this->assertSame( "<{$style}>Title</{$style}>\n", $result, "Failed for style {$style}" );
		}
	}

	public function test_render_blockquote(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'bq',
				'style'    => 'blockquote',
				'children' => [
					[ '_type' => 'span', '_key' => 's', 'text' => 'A quote', 'marks' => [] ],
				],
				'markDefs' => [],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertSame( "<blockquote><p>A quote</p></blockquote>\n", $result );
	}

	// --- Decorators ---

	public function test_render_bold(): void {
		$result = $this->renderWithDecorator( 'strong', 'Bold text' );
		$this->assertSame( "<p><strong>Bold text</strong></p>\n", $result );
	}

	public function test_render_italic(): void {
		$result = $this->renderWithDecorator( 'em', 'Italic text' );
		$this->assertSame( "<p><em>Italic text</em></p>\n", $result );
	}

	public function test_render_underline(): void {
		$result = $this->renderWithDecorator( 'underline', 'Underlined' );
		$this->assertSame( "<p><u>Underlined</u></p>\n", $result );
	}

	public function test_render_strikethrough(): void {
		$result = $this->renderWithDecorator( 'strike-through', 'Struck' );
		$this->assertSame( "<p><s>Struck</s></p>\n", $result );
	}

	public function test_render_code(): void {
		$result = $this->renderWithDecorator( 'code', 'inline code' );
		$this->assertSame( "<p><code>inline code</code></p>\n", $result );
	}

	public function test_render_subscript(): void {
		$result = $this->renderWithDecorator( 'subscript', '2' );
		$this->assertSame( "<p><sub>2</sub></p>\n", $result );
	}

	public function test_render_superscript(): void {
		$result = $this->renderWithDecorator( 'superscript', '2' );
		$this->assertSame( "<p><sup>2</sup></p>\n", $result );
	}

	// --- Links ---

	public function test_render_link_annotation(): void {
		Functions\expect( 'home_url' )->andReturn( 'https://example.com' );

		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'normal',
				'children' => [
					[
						'_type' => 'span',
						'_key'  => 's1',
						'text'  => 'click here',
						'marks' => [ 'link1' ],
					],
				],
				'markDefs' => [
					[
						'_key'  => 'link1',
						'_type' => 'link',
						'href'  => 'https://other.com',
					],
				],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<a href="https://other.com"', $result );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $result );
		$this->assertStringContainsString( 'click here</a>', $result );
	}

	public function test_render_link_rejects_javascript_uri(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'normal',
				'children' => [
					[
						'_type' => 'span',
						'_key'  => 's1',
						'text'  => 'xss',
						'marks' => [ 'link1' ],
					],
				],
				'markDefs' => [
					[
						'_key'  => 'link1',
						'_type' => 'link',
						'href'  => 'javascript:alert(1)',
					],
				],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringNotContainsString( 'javascript:', $result );
		$this->assertStringNotContainsString( '<a', $result );
	}

	public function test_render_link_rejects_data_uri(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'normal',
				'children' => [
					[
						'_type' => 'span',
						'_key'  => 's1',
						'text'  => 'xss',
						'marks' => [ 'link1' ],
					],
				],
				'markDefs' => [
					[
						'_key'  => 'link1',
						'_type' => 'link',
						'href'  => 'data:text/html,<script>alert(1)</script>',
					],
				],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringNotContainsString( 'data:', $result );
		$this->assertStringNotContainsString( '<a', $result );
	}

	// --- Block objects ---

	public function test_render_hr_break(): void {
		$json = json_encode( [
			[ '_type' => 'break', '_key' => 'br1' ],
		] );

		$result = $this->renderer->render( $json );
		$this->assertSame( "<hr />\n", $result );
	}

	public function test_render_image_block(): void {
		$json = json_encode( [
			[
				'_type'   => 'image',
				'_key'    => 'img1',
				'src'     => 'https://example.com/photo.jpg',
				'alt'     => 'A photo',
				'caption' => 'My caption',
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<figure', $result );
		$this->assertStringContainsString( 'src="https://example.com/photo.jpg"', $result );
		$this->assertStringContainsString( 'alt="A photo"', $result );
		$this->assertStringContainsString( '<figcaption>My caption</figcaption>', $result );
	}

	public function test_render_image_block_no_caption(): void {
		$json = json_encode( [
			[
				'_type' => 'image',
				'_key'  => 'img2',
				'src'   => 'https://example.com/photo.jpg',
				'alt'   => '',
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringNotContainsString( '<figcaption>', $result );
	}

	public function test_render_image_block_empty_src(): void {
		$json = json_encode( [
			[
				'_type' => 'image',
				'_key'  => 'img3',
				'src'   => '',
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertSame( '', $result );
	}

	public function test_render_code_block(): void {
		$json = json_encode( [
			[
				'_type'    => 'codeBlock',
				'_key'     => 'cb1',
				'code'     => 'echo "hello";',
				'language' => 'php',
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<pre><code class="language-php">', $result );
		$this->assertStringContainsString( 'echo &quot;hello&quot;;', $result );
	}

	public function test_render_code_block_no_language(): void {
		$json = json_encode( [
			[
				'_type' => 'codeBlock',
				'_key'  => 'cb2',
				'code'  => 'hello',
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<pre><code>', $result );
		$this->assertStringNotContainsString( 'class="language-"', $result );
	}

	// --- Lists ---

	public function test_render_bullet_list(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'li1',
				'style'    => 'normal',
				'listItem' => 'bullet',
				'level'    => 1,
				'children' => [ [ '_type' => 'span', 'text' => 'Item one', 'marks' => [] ] ],
				'markDefs' => [],
			],
			[
				'_type'    => 'block',
				'_key'     => 'li2',
				'style'    => 'normal',
				'listItem' => 'bullet',
				'level'    => 1,
				'children' => [ [ '_type' => 'span', 'text' => 'Item two', 'marks' => [] ] ],
				'markDefs' => [],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<ul>', $result );
		$this->assertStringContainsString( '<li>Item one</li>', $result );
		$this->assertStringContainsString( '<li>Item two</li>', $result );
		$this->assertStringContainsString( '</ul>', $result );
	}

	public function test_render_numbered_list(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'li1',
				'style'    => 'normal',
				'listItem' => 'number',
				'level'    => 1,
				'children' => [ [ '_type' => 'span', 'text' => 'First', 'marks' => [] ] ],
				'markDefs' => [],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<ol>', $result );
		$this->assertStringContainsString( '<li>First</li>', $result );
		$this->assertStringContainsString( '</ol>', $result );
	}

	// --- XSS protection ---

	public function test_render_escapes_html_in_text(): void {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'x1',
				'style'    => 'normal',
				'children' => [
					[ '_type' => 'span', '_key' => 's', 'text' => '<script>alert("xss")</script>', 'marks' => [] ],
				],
				'markDefs' => [],
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	public function test_render_escapes_html_in_code_block(): void {
		$json = json_encode( [
			[
				'_type' => 'codeBlock',
				'_key'  => 'cb',
				'code'  => '<script>alert(1)</script>',
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringNotContainsString( '<script>alert', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	// --- Mixed content ---

	public function test_render_mixed_blocks(): void {
		Functions\expect( 'home_url' )->andReturn( 'https://example.com' );

		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'h2',
				'children' => [ [ '_type' => 'span', 'text' => 'Title', 'marks' => [] ] ],
				'markDefs' => [],
			],
			[
				'_type'    => 'block',
				'_key'     => 'b2',
				'style'    => 'normal',
				'children' => [ [ '_type' => 'span', 'text' => 'Paragraph', 'marks' => [] ] ],
				'markDefs' => [],
			],
			[ '_type' => 'break', '_key' => 'br' ],
			[
				'_type' => 'image',
				'_key'  => 'im',
				'src'   => 'https://example.com/img.jpg',
				'alt'   => 'test',
			],
		] );

		$result = $this->renderer->render( $json );
		$this->assertStringContainsString( '<h2>Title</h2>', $result );
		$this->assertStringContainsString( '<p>Paragraph</p>', $result );
		$this->assertStringContainsString( '<hr />', $result );
		$this->assertStringContainsString( '<img', $result );
	}

	// --- Helper ---

	private function renderWithDecorator( string $decorator, string $text ): string {
		$json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'normal',
				'children' => [
					[
						'_type' => 'span',
						'_key'  => 's1',
						'text'  => $text,
						'marks' => [ $decorator ],
					],
				],
				'markDefs' => [],
			],
		] );

		return $this->renderer->render( $json );
	}

	// ---- Markdown rendering ----

	public function test_markdown_paragraph(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'normal',
				'children' => [ [ '_type' => 'span', 'text' => 'Hello world', 'marks' => [] ] ],
				'markDefs' => [],
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertSame( "Hello world\n", $result );
	}

	public function test_markdown_headings(): void {
		foreach ( range( 1, 6 ) as $level ) {
			$blocks = [
				[
					'_type'    => 'block',
					'_key'     => "h{$level}",
					'style'    => "h{$level}",
					'children' => [ [ '_type' => 'span', 'text' => 'Heading', 'marks' => [] ] ],
					'markDefs' => [],
				],
			];

			$prefix = str_repeat( '#', $level );
			$result = $this->renderer->blocks_to_markdown( $blocks );
			$this->assertStringContainsString( "{$prefix} Heading", $result );
		}
	}

	public function test_markdown_blockquote(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'_key'     => 'bq1',
				'style'    => 'blockquote',
				'children' => [ [ '_type' => 'span', 'text' => 'Quoted text', 'marks' => [] ] ],
				'markDefs' => [],
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '> Quoted text', $result );
	}

	public function test_markdown_decorators(): void {
		$cases = [
			'strong'         => '**bold**',
			'em'             => '*italic*',
			'code'           => '`code`',
			'strike-through' => '~~strike~~',
		];

		foreach ( $cases as $decorator => $expected ) {
			$blocks = [
				[
					'_type'    => 'block',
					'_key'     => 'd1',
					'style'    => 'normal',
					'children' => [
						[ '_type' => 'span', 'text' => array_values( array_filter( [ 'strong' => 'bold', 'em' => 'italic', 'code' => 'code', 'strike-through' => 'strike' ] ) )[ array_search( $decorator, array_keys( $cases ), true ) ], 'marks' => [ $decorator ] ],
					],
					'markDefs' => [],
				],
			];

			$result = $this->renderer->blocks_to_markdown( $blocks );
			$this->assertStringContainsString( $expected, $result, "Decorator {$decorator}" );
		}
	}

	public function test_markdown_link(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'normal',
				'children' => [
					[ '_type' => 'span', 'text' => 'click here', 'marks' => [ 'link1' ] ],
				],
				'markDefs' => [
					[ '_key' => 'link1', '_type' => 'link', 'href' => 'https://example.com' ],
				],
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '[click here](https://example.com)', $result );
	}

	public function test_markdown_image(): void {
		$blocks = [
			[
				'_type' => 'image',
				'_key'  => 'img1',
				'src'   => 'https://example.com/photo.jpg',
				'alt'   => 'A photo',
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '![A photo](https://example.com/photo.jpg)', $result );
	}

	public function test_markdown_image_with_caption(): void {
		$blocks = [
			[
				'_type'   => 'image',
				'_key'    => 'img1',
				'src'     => 'https://example.com/photo.jpg',
				'alt'     => 'A photo',
				'caption' => 'My caption',
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '*My caption*', $result );
	}

	public function test_markdown_code_block(): void {
		$blocks = [
			[
				'_type'    => 'codeBlock',
				'_key'     => 'cb1',
				'code'     => 'echo "hello";',
				'language' => 'php',
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( "```php\necho \"hello\";\n```", $result );
	}

	public function test_markdown_hr(): void {
		$blocks = [ [ '_type' => 'break', '_key' => 'br1' ] ];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '---', $result );
	}

	public function test_markdown_bullet_list(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'_key'     => 'li1',
				'style'    => 'normal',
				'listItem' => 'bullet',
				'level'    => 1,
				'children' => [ [ '_type' => 'span', 'text' => 'Item A', 'marks' => [] ] ],
				'markDefs' => [],
			],
			[
				'_type'    => 'block',
				'_key'     => 'li2',
				'style'    => 'normal',
				'listItem' => 'bullet',
				'level'    => 1,
				'children' => [ [ '_type' => 'span', 'text' => 'Item B', 'marks' => [] ] ],
				'markDefs' => [],
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '- Item A', $result );
		$this->assertStringContainsString( '- Item B', $result );
	}

	public function test_markdown_numbered_list(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'_key'     => 'li1',
				'style'    => 'normal',
				'listItem' => 'number',
				'level'    => 1,
				'children' => [ [ '_type' => 'span', 'text' => 'First', 'marks' => [] ] ],
				'markDefs' => [],
			],
			[
				'_type'    => 'block',
				'_key'     => 'li2',
				'style'    => 'normal',
				'listItem' => 'number',
				'level'    => 1,
				'children' => [ [ '_type' => 'span', 'text' => 'Second', 'marks' => [] ] ],
				'markDefs' => [],
			],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '1. First', $result );
		$this->assertStringContainsString( '2. Second', $result );
	}

	public function test_markdown_mixed_blocks(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'_key'     => 'b1',
				'style'    => 'h2',
				'children' => [ [ '_type' => 'span', 'text' => 'Title', 'marks' => [] ] ],
				'markDefs' => [],
			],
			[
				'_type'    => 'block',
				'_key'     => 'b2',
				'style'    => 'normal',
				'children' => [ [ '_type' => 'span', 'text' => 'Paragraph', 'marks' => [] ] ],
				'markDefs' => [],
			],
			[ '_type' => 'break', '_key' => 'br' ],
		];

		$result = $this->renderer->blocks_to_markdown( $blocks );
		$this->assertStringContainsString( '## Title', $result );
		$this->assertStringContainsString( "Paragraph\n", $result );
		$this->assertStringContainsString( '---', $result );
	}
}
