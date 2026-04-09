<?php
/**
 * Tests for WPPortableText\Content_Filter.
 */

declare( strict_types=1 );

namespace WPPortableText\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WPPortableText\Content_Filter;

class ContentFilterTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private Content_Filter $filter;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->filter = new Content_Filter();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- is_portable_text ---

	public function test_is_portable_text_returns_true_for_empty_array(): void {
		$this->assertTrue( $this->filter->is_portable_text( [] ) );
	}

	public function test_is_portable_text_returns_true_for_valid_pt(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'_key'     => 'abc',
				'children' => [
					[ '_type' => 'span', 'text' => 'Hello' ],
				],
			],
		];
		$this->assertTrue( $this->filter->is_portable_text( $blocks ) );
	}

	public function test_is_portable_text_returns_false_for_assoc_array(): void {
		$this->assertFalse( $this->filter->is_portable_text( [ 'foo' => 'bar' ] ) );
	}

	public function test_is_portable_text_returns_false_for_no_type(): void {
		$this->assertFalse( $this->filter->is_portable_text( [ [ 'text' => 'no type' ] ] ) );
	}

	// --- extract_plaintext ---

	public function test_extract_plaintext_returns_empty_for_empty_doc(): void {
		$this->assertSame( '', $this->filter->extract_plaintext( [] ) );
	}

	public function test_extract_plaintext_concatenates_spans(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'children' => [
					[ '_type' => 'span', 'text' => 'Hello ' ],
					[ '_type' => 'span', 'text' => 'world' ],
				],
			],
		];
		$this->assertSame( 'Hello world', $this->filter->extract_plaintext( $blocks ) );
	}

	public function test_extract_plaintext_joins_blocks_with_double_newlines(): void {
		$blocks = [
			[
				'_type'    => 'block',
				'children' => [ [ '_type' => 'span', 'text' => 'Paragraph one' ] ],
			],
			[
				'_type'    => 'block',
				'children' => [ [ '_type' => 'span', 'text' => 'Paragraph two' ] ],
			],
		];
		$this->assertSame( "Paragraph one\n\nParagraph two", $this->filter->extract_plaintext( $blocks ) );
	}

	public function test_extract_plaintext_skips_non_block_types(): void {
		$blocks = [
			[
				'_type' => 'image',
				'src'   => 'https://example.com/img.jpg',
			],
			[
				'_type'    => 'block',
				'children' => [ [ '_type' => 'span', 'text' => 'Text' ] ],
			],
		];
		$this->assertSame( 'Text', $this->filter->extract_plaintext( $blocks ) );
	}

	// --- register ---

	public function test_register_hooks_wp_insert_post_data(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'wp_insert_post_data', [ $this->filter, 'filter_post_data' ], 10, 2 );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'wp_insert_post_data', [ $this->filter, 'populate_content_filtered' ], 20, 2 );

		$this->filter->register();
	}

	// --- filter_post_data ---

	public function test_filter_post_data_skips_when_no_nonce(): void {
		$data    = [ 'post_content' => '<p>Hello</p>' ];
		$postarr = [];

		$result = $this->filter->filter_post_data( $data, $postarr );
		$this->assertSame( $data, $result );
	}

	public function test_filter_post_data_skips_when_nonce_invalid(): void {
		Functions\expect( 'wp_verify_nonce' )->once()->andReturn( false );

		$data    = [ 'post_content' => '[]' ];
		$postarr = [ 'wp_portable_text_nonce' => 'bad' ];

		$result = $this->filter->filter_post_data( $data, $postarr );
		$this->assertSame( $data, $result );
	}

	public function test_filter_post_data_reencodes_valid_pt_json(): void {
		$pt_json = json_encode( [
			[
				'_type'    => 'block',
				'_key'     => 'a1',
				'children' => [ [ '_type' => 'span', 'text' => 'Hello', 'marks' => [] ] ],
			],
		] );

		Functions\expect( 'wp_verify_nonce' )->once()->andReturn( 1 );
		Functions\expect( 'get_post_type_object' )->once()->andReturn(
			(object) [ 'cap' => (object) [ 'edit_posts' => 'edit_posts' ] ]
		);
		Functions\expect( 'current_user_can' )->once()->with( 'edit_posts' )->andReturn( true );
		Functions\expect( 'wp_unslash' )->once()->andReturnUsing( function ( $v ) { return $v; } );
		Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( function ( $v, $opts ) {
			return json_encode( $v, $opts );
		} );
		Functions\expect( 'wp_slash' )->once()->andReturnUsing( function ( $v ) { return $v; } );

		$data    = [ 'post_content' => $pt_json, 'post_type' => 'post' ];
		$postarr = [ 'wp_portable_text_nonce' => 'valid' ];

		$result = $this->filter->filter_post_data( $data, $postarr );

		// Content should be re-encoded JSON.
		$decoded = json_decode( $result['post_content'], true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'block', $decoded[0]['_type'] );
	}

	public function test_filter_post_data_skips_non_json_content(): void {
		Functions\expect( 'wp_verify_nonce' )->once()->andReturn( 1 );
		Functions\expect( 'get_post_type_object' )->once()->andReturn(
			(object) [ 'cap' => (object) [ 'edit_posts' => 'edit_posts' ] ]
		);
		Functions\expect( 'current_user_can' )->once()->andReturn( true );
		Functions\expect( 'wp_unslash' )->once()->andReturnUsing( function ( $v ) { return $v; } );

		$data    = [ 'post_content' => '<p>Not JSON</p>', 'post_type' => 'post' ];
		$postarr = [ 'wp_portable_text_nonce' => 'valid' ];

		$result = $this->filter->filter_post_data( $data, $postarr );
		$this->assertSame( '<p>Not JSON</p>', $result['post_content'] );
	}

	public function test_filter_post_data_rejects_insufficient_caps(): void {
		Functions\expect( 'wp_verify_nonce' )->once()->andReturn( 1 );
		Functions\expect( 'get_post_type_object' )->once()->andReturn(
			(object) [ 'cap' => (object) [ 'edit_posts' => 'edit_posts' ] ]
		);
		Functions\expect( 'current_user_can' )->once()->with( 'edit_posts' )->andReturn( false );

		$data    = [ 'post_content' => '[]', 'post_type' => 'post' ];
		$postarr = [ 'wp_portable_text_nonce' => 'valid' ];

		$result = $this->filter->filter_post_data( $data, $postarr );
		$this->assertSame( $data, $result );
	}

	// --- populate_content_filtered ---

	public function test_populate_content_filtered_extracts_plaintext(): void {
		$pt_json = json_encode( [
			[
				'_type'    => 'block',
				'children' => [ [ '_type' => 'span', 'text' => 'Search me' ] ],
			],
		] );

		Functions\expect( 'wp_unslash' )->once()->andReturnUsing( function ( $v ) { return $v; } );
		Functions\expect( 'wp_slash' )->once()->andReturnUsing( function ( $v ) { return $v; } );

		$data    = [ 'post_content' => $pt_json ];
		$postarr = [];

		$result = $this->filter->populate_content_filtered( $data, $postarr );
		$this->assertSame( 'Search me', $result['post_content_filtered'] );
	}

	public function test_populate_content_filtered_skips_non_pt(): void {
		Functions\expect( 'wp_unslash' )->once()->andReturnUsing( function ( $v ) { return $v; } );

		$data    = [ 'post_content' => '<p>HTML</p>' ];
		$postarr = [];

		$result = $this->filter->populate_content_filtered( $data, $postarr );
		$this->assertArrayNotHasKey( 'post_content_filtered', $result );
	}
}
