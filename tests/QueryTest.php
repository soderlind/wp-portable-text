<?php
/**
 * Tests for WPPortableText\Query.
 */

declare( strict_types=1 );

namespace WPPortableText\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WPPortableText\Query;

/**
 * Testable subclass that overrides create_wp_query.
 */
class TestableQuery extends Query {

	/** @var object[] */
	public array $mock_posts = [];

	public int $mock_found_posts = 0;

	public int $mock_max_num_pages = 1;

	protected function create_wp_query( array $args ): object {
		return (object) [
			'posts'         => $this->mock_posts,
			'found_posts'   => $this->mock_found_posts,
			'max_num_pages' => $this->mock_max_num_pages,
		];
	}
}

class QueryTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private TestableQuery $query;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->query = new TestableQuery();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- register ---

	public function test_register_hooks(): void {
		$real_query = new Query();

		Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', [ $real_query, 'register_routes' ] );

		$real_query->register();
	}

	// --- handle_query: filtering logic ---

	/**
	 * Helper: create a mock WP_REST_Request with parameters.
	 *
	 * @param array<string,mixed> $params Query parameters.
	 * @return \WP_REST_Request
	 */
	private function make_request( array $params = [] ): \WP_REST_Request {
		$request = \Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )
			->andReturnUsing( function ( string $key ) use ( $params ) {
				return $params[ $key ] ?? null;
			} );
		return $request;
	}

	/**
	 * Helper: create a mock WP_Post with PT JSON content.
	 *
	 * @param int                          $id      Post ID.
	 * @param array<int,array<string,mixed>> $blocks  PT blocks.
	 * @param string                       $title   Post title.
	 * @return object
	 */
	private function make_post( int $id, array $blocks, string $title = 'Test Post' ): object {
		return (object) [
			'ID'              => $id,
			'post_content'    => json_encode( $blocks ),
			'post_title'      => $title,
			'post_date_gmt'   => '2026-04-10 12:00:00',
		];
	}

	/**
	 * Helper: a simple text block.
	 */
	private function text_block( string $text, string $style = 'normal', array $marks = [], array $mark_defs = [] ): array {
		return [
			'_type'    => 'block',
			'_key'     => uniqid(),
			'style'    => $style,
			'children' => [
				[
					'_type' => 'span',
					'_key'  => uniqid(),
					'text'  => $text,
					'marks' => $marks,
				],
			],
			'markDefs' => $mark_defs,
		];
	}

	/**
	 * Helper: an image block.
	 */
	private function image_block( string $src = 'https://example.com/img.jpg', string $alt = 'Test' ): array {
		return [
			'_type' => 'image',
			'_key'  => uniqid(),
			'src'   => $src,
			'alt'   => $alt,
		];
	}

	/**
	 * Helper: a code block.
	 */
	private function code_block( string $code = 'echo "hi"', string $language = 'php' ): array {
		return [
			'_type'    => 'codeBlock',
			'_key'     => uniqid(),
			'code'     => $code,
			'language' => $language,
		];
	}

	/**
	 * Helper: a text block with a link annotation.
	 */
	private function linked_block( string $text, string $href ): array {
		$key = uniqid();
		return [
			'_type'    => 'block',
			'_key'     => uniqid(),
			'style'    => 'normal',
			'children' => [
				[
					'_type' => 'span',
					'_key'  => uniqid(),
					'text'  => $text,
					'marks' => [ $key ],
				],
			],
			'markDefs' => [
				[
					'_type' => 'link',
					'_key'  => $key,
					'href'  => $href,
				],
			],
		];
	}

	// --- handle_query with mocked WP_Query ---

	/**
	 * Stub WP functions used in handle_query / handle_blocks.
	 */
	private function stub_wp_functions(): void {
		Functions\stubs( [
			'get_the_title' => static function ( $post ): string {
				return is_object( $post ) ? $post->post_title : 'Post ' . $post;
			},
			'get_permalink'  => static function ( $post ): string {
				$id = is_object( $post ) ? $post->ID : $post;
				return "https://example.com/?p={$id}";
			},
			'sanitize_key'        => static function ( string $s ): string { return strtolower( $s ); },
			'sanitize_text_field' => static function ( string $s ): string { return $s; },
			'absint'              => static function ( $n ): int { return abs( (int) $n ); },
		] );
	}

	public function test_handle_query_returns_all_pt_posts(): void {
		$this->stub_wp_functions();

		$blocks = [ $this->text_block( 'Hello' ) ];
		$post   = $this->make_post( 1, $blocks, 'My Post' );

		$this->query->mock_posts         = [ $post ];
		$this->query->mock_found_posts   = 1;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request( [ 'post_type' => 'post' ] );
		$response = $this->query->handle_query( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 1, $data[0]['id'] );
		$this->assertSame( 'My Post', $data[0]['title'] );
		$this->assertIsArray( $data[0]['portable_text'] );
	}

	public function test_handle_query_filters_by_block_type(): void {
		$this->stub_wp_functions();

		$post_with_image = $this->make_post( 1, [
			$this->text_block( 'Text' ),
			$this->image_block(),
		], 'Has Image' );

		$post_without_image = $this->make_post( 2, [
			$this->text_block( 'Only text' ),
		], 'Text Only' );

		$this->query->mock_posts         = [ $post_with_image, $post_without_image ];
		$this->query->mock_found_posts   = 2;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request( [ 'block_type' => 'image' ] );
		$response = $this->query->handle_query( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( 'Has Image', $data[0]['title'] );
		$this->assertCount( 1, $data[0]['matched_blocks'] );
		$this->assertSame( 'image', $data[0]['matched_blocks'][0]['_type'] );
	}

	public function test_handle_query_filters_by_has_link(): void {
		$this->stub_wp_functions();

		$post_with_link = $this->make_post( 1, [
			$this->linked_block( 'Click me', 'https://example.com' ),
		], 'Has Link' );

		$post_without_link = $this->make_post( 2, [
			$this->text_block( 'No links' ),
		], 'No Link' );

		$this->query->mock_posts         = [ $post_with_link, $post_without_link ];
		$this->query->mock_found_posts   = 2;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request( [ 'has' => 'link' ] );
		$response = $this->query->handle_query( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( 'Has Link', $data[0]['title'] );
	}

	public function test_handle_query_filters_by_style(): void {
		$this->stub_wp_functions();

		$post_with_h2 = $this->make_post( 1, [
			$this->text_block( 'Heading', 'h2' ),
			$this->text_block( 'Body' ),
		], 'Has H2' );

		$post_no_h2 = $this->make_post( 2, [
			$this->text_block( 'Only body' ),
		], 'No H2' );

		$this->query->mock_posts         = [ $post_with_h2, $post_no_h2 ];
		$this->query->mock_found_posts   = 2;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request( [ 'style' => 'h2' ] );
		$response = $this->query->handle_query( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( 'Has H2', $data[0]['title'] );
	}

	public function test_handle_query_filters_by_has_strong(): void {
		$this->stub_wp_functions();

		$post_bold = $this->make_post( 1, [
			$this->text_block( 'Bold text', 'normal', [ 'strong' ] ),
		], 'Has Bold' );

		$post_plain = $this->make_post( 2, [
			$this->text_block( 'Plain text' ),
		], 'No Bold' );

		$this->query->mock_posts         = [ $post_bold, $post_plain ];
		$this->query->mock_found_posts   = 2;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request( [ 'has' => 'strong' ] );
		$response = $this->query->handle_query( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( 'Has Bold', $data[0]['title'] );
	}

	public function test_handle_query_skips_non_pt_content(): void {
		$this->stub_wp_functions();

		$html_post = (object) [
			'ID'            => 1,
			'post_content'  => '<p>Regular HTML content</p>',
			'post_title'    => 'HTML Post',
			'post_date_gmt' => '2026-04-10 12:00:00',
		];

		$empty_post = (object) [
			'ID'            => 2,
			'post_content'  => '',
			'post_title'    => 'Empty Post',
			'post_date_gmt' => '2026-04-10 12:00:00',
		];

		$this->query->mock_posts         = [ $html_post, $empty_post ];
		$this->query->mock_found_posts   = 2;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request();
		$response = $this->query->handle_query( $request );
		$data     = $response->get_data();

		$this->assertCount( 0, $data );
	}

	public function test_handle_query_pagination_headers(): void {
		$this->stub_wp_functions();

		$this->query->mock_posts         = [];
		$this->query->mock_found_posts   = 25;
		$this->query->mock_max_num_pages = 3;

		$request  = $this->make_request( [ 'per_page' => 10, 'page' => 1 ] );
		$response = $this->query->handle_query( $request );

		$headers = $response->get_headers();
		$this->assertSame( '25', $headers['X-WP-Total'] );
		$this->assertSame( '3', $headers['X-WP-TotalPages'] );
	}

	// --- handle_blocks ---

	public function test_handle_blocks_extracts_images(): void {
		$this->stub_wp_functions();

		$post = $this->make_post( 1, [
			$this->text_block( 'Hello' ),
			$this->image_block( 'https://example.com/a.jpg', 'Photo A' ),
			$this->text_block( 'World' ),
			$this->image_block( 'https://example.com/b.jpg', 'Photo B' ),
		], 'Gallery Post' );

		$this->query->mock_posts         = [ $post ];
		$this->query->mock_found_posts   = 1;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request( [ 'block_type' => 'image' ] );
		$response = $this->query->handle_blocks( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertSame( 'image', $data[0]['block']['_type'] );
		$this->assertSame( 'https://example.com/a.jpg', $data[0]['block']['src'] );
		$this->assertSame( 1, $data[0]['post_id'] );
		$this->assertSame( 'image', $data[1]['block']['_type'] );
		$this->assertSame( 'https://example.com/b.jpg', $data[1]['block']['src'] );
	}

	public function test_handle_blocks_filters_code_by_language(): void {
		$this->stub_wp_functions();

		$post = $this->make_post( 1, [
			$this->code_block( 'echo "hi"', 'php' ),
			$this->code_block( 'console.log("hi")', 'javascript' ),
			$this->code_block( '$x = 1', 'php' ),
		], 'Multi Code' );

		$this->query->mock_posts         = [ $post ];
		$this->query->mock_found_posts   = 1;
		$this->query->mock_max_num_pages = 1;

		$request  = $this->make_request( [ 'block_type' => 'codeBlock', 'language' => 'php' ] );
		$response = $this->query->handle_blocks( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertSame( 'php', $data[0]['block']['language'] );
		$this->assertSame( 'php', $data[1]['block']['language'] );
	}

	public function test_handle_blocks_pagination(): void {
		$this->stub_wp_functions();

		$blocks = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$blocks[] = $this->image_block( "https://example.com/{$i}.jpg", "Image {$i}" );
		}

		$post = $this->make_post( 1, $blocks, 'Many Images' );

		$this->query->mock_posts         = [ $post ];
		$this->query->mock_found_posts   = 1;
		$this->query->mock_max_num_pages = 1;

		// Page 1 with per_page=2.
		$request  = $this->make_request( [ 'block_type' => 'image', 'per_page' => 2, 'page' => 1 ] );
		$response = $this->query->handle_blocks( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data );
		$headers = $response->get_headers();
		$this->assertSame( '5', $headers['X-WP-Total'] );
		$this->assertSame( '3', $headers['X-WP-TotalPages'] );

		// Page 3: only 1 remaining.
		$request  = $this->make_request( [ 'block_type' => 'image', 'per_page' => 2, 'page' => 3 ] );
		$response = $this->query->handle_blocks( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
	}
}
