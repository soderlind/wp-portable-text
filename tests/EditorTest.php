<?php
/**
 * Tests for WPPortableText\Editor.
 */

declare( strict_types=1 );

namespace WPPortableText\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WPPortableText\Editor;

class EditorTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private Editor $editor;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->editor = new Editor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- register ---

	public function test_register_adds_expected_hooks(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'use_block_editor_for_post', [ $this->editor, 'disable_block_editor' ], 100, 2 );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_init', [ $this->editor, 'remove_classic_editor' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'edit_form_after_title', [ $this->editor, 'render_editor' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_enqueue_scripts', [ $this->editor, 'enqueue_assets' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'add_meta_boxes', [ $this->editor, 'adjust_meta_boxes' ] );

		$this->editor->register();
	}

	// --- disable_block_editor ---

	public function test_disable_block_editor_returns_false_for_post(): void {
		$post = (object) [ 'ID' => 1, 'post_type' => 'post' ];
		Functions\expect( 'get_post_type' )->once()->with( $post )->andReturn( 'post' );

		$this->assertFalse( $this->editor->disable_block_editor( true, $post ) );
	}

	public function test_disable_block_editor_returns_false_for_page(): void {
		$post = (object) [ 'ID' => 2, 'post_type' => 'page' ];
		Functions\expect( 'get_post_type' )->once()->with( $post )->andReturn( 'page' );

		$this->assertFalse( $this->editor->disable_block_editor( true, $post ) );
	}

	public function test_disable_block_editor_preserves_for_wp_navigation(): void {
		$post = (object) [ 'ID' => 3, 'post_type' => 'wp_navigation' ];
		Functions\expect( 'get_post_type' )->once()->with( $post )->andReturn( 'wp_navigation' );

		$this->assertTrue( $this->editor->disable_block_editor( true, $post ) );
	}

	public function test_disable_block_editor_preserves_for_wp_template(): void {
		$post = (object) [ 'ID' => 4, 'post_type' => 'wp_template' ];
		Functions\expect( 'get_post_type' )->once()->with( $post )->andReturn( 'wp_template' );

		$this->assertTrue( $this->editor->disable_block_editor( true, $post ) );
	}

	public function test_disable_block_editor_preserves_for_wp_template_part(): void {
		$post = (object) [ 'ID' => 5, 'post_type' => 'wp_template_part' ];
		Functions\expect( 'get_post_type' )->once()->with( $post )->andReturn( 'wp_template_part' );

		$this->assertTrue( $this->editor->disable_block_editor( true, $post ) );
	}

	public function test_disable_block_editor_preserves_for_wp_global_styles(): void {
		$post = (object) [ 'ID' => 6, 'post_type' => 'wp_global_styles' ];
		Functions\expect( 'get_post_type' )->once()->with( $post )->andReturn( 'wp_global_styles' );

		$this->assertTrue( $this->editor->disable_block_editor( true, $post ) );
	}

	public function test_disable_block_editor_preserves_for_wp_block(): void {
		$post = (object) [ 'ID' => 7, 'post_type' => 'wp_block' ];
		Functions\expect( 'get_post_type' )->once()->with( $post )->andReturn( 'wp_block' );

		$this->assertTrue( $this->editor->disable_block_editor( true, $post ) );
	}

	// --- remove_classic_editor ---

	public function test_remove_classic_editor_skips_excluded_types(): void {
		Functions\expect( 'get_post_types' )
			->once()
			->with( [ 'show_ui' => true ] )
			->andReturn( [ 'post' => 'post', 'page' => 'page', 'wp_navigation' => 'wp_navigation' ] );

		Functions\expect( 'post_type_supports' )
			->with( 'post', 'editor' )
			->andReturn( true );
		Functions\expect( 'post_type_supports' )
			->with( 'page', 'editor' )
			->andReturn( true );
		// wp_navigation should NOT be checked for editor support.

		Functions\expect( 'remove_post_type_support' )
			->once()
			->with( 'post', 'editor' );
		Functions\expect( 'remove_post_type_support' )
			->once()
			->with( 'page', 'editor' );

		$this->editor->remove_classic_editor();
	}

	// --- enqueue_assets ---

	public function test_enqueue_assets_skips_non_edit_screens(): void {
		// Should not call wp_enqueue_media on non-edit pages.
		Functions\expect( 'wp_enqueue_media' )->never();

		$this->editor->enqueue_assets( 'edit.php' );
	}
}
