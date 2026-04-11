<?php
/**
 * Content filter — bypass kses for Portable Text JSON, validate structure.
 *
 * @package WPPortableText
 */

declare( strict_types=1 );

namespace WPPortableText;

/**
 * Filters post content on save/load for PT JSON compatibility.
 */
class Content_Filter {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Bypass kses and balanceTags for PT JSON content on save.
		add_filter( 'wp_insert_post_data', [ $this, 'filter_post_data' ], 10, 2 );

		// Store plaintext in post_content_filtered for search.
		add_filter( 'wp_insert_post_data', [ $this, 'populate_content_filtered' ], 20, 2 );
	}

	/**
	 * Prevent kses from mangling PT JSON on save.
	 *
	 * WP runs content_save_pre (which fires wp_filter_post_kses) and balanceTags
	 * on post_content. When the content is PT JSON, this would corrupt it.
	 *
	 * We detect the nonce from our editor, validate the JSON structure,
	 * and re-set post_content to the raw JSON (bypassing kses result).
	 *
	 * @param array<string,mixed> $data    Slashed post data.
	 * @param array<string,mixed> $postarr Raw post array from $_POST.
	 * @return array<string,mixed>
	 */
	public function filter_post_data( array $data, array $postarr ): array {
		// Only act when our editor submitted the form.
		if ( empty( $postarr['wp_portable_text_nonce'] ) ) {
			return $data;
		}

		if ( ! wp_verify_nonce( $postarr['wp_portable_text_nonce'], 'wp_portable_text_save' ) ) {
			return $data;
		}

		// Capability check — must be able to edit posts.
		$post_type = $data['post_type'] ?? 'post';
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			return $data;
		}

		$raw_content = wp_unslash( $data['post_content'] );

		// Validate that it's a JSON array (PT document).
		$decoded = json_decode( $raw_content, true );
		if ( ! is_array( $decoded ) || ! $this->is_portable_text( $decoded ) ) {
			return $data;
		}

		// Re-encode with consistent formatting and re-slash for WP.
		$clean_json           = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$data['post_content'] = wp_slash( $clean_json );

		return $data;
	}

	/**
	 * Populate post_content_filtered with plaintext for search.
	 *
	 * @param array<string,mixed> $data    Slashed post data.
	 * @param array<string,mixed> $postarr Raw post array.
	 * @return array<string,mixed>
	 */
	public function populate_content_filtered( array $data, array $postarr ): array {
		$raw_content = wp_unslash( $data['post_content'] );
		$decoded     = json_decode( $raw_content, true );

		if ( is_array( $decoded ) && $this->is_portable_text( $decoded ) ) {
			$plaintext                         = $this->extract_plaintext( $decoded );
			$data['post_content_filtered'] = wp_slash( $plaintext );
		}

		return $data;
	}

	/**
	 * Check if a decoded JSON array looks like a Portable Text document.
	 *
	 * Minimal check: must be a sequential array where at least the first
	 * element has a _type key.
	 *
	 * @param array<int|string,mixed> $blocks Decoded JSON.
	 * @return bool
	 */
	public function is_portable_text( array $blocks ): bool {
		if ( empty( $blocks ) ) {
			return true; // Empty document is valid PT.
		}

		// Must be a sequential (list) array.
		if ( ! array_is_list( $blocks ) ) {
			return false;
		}

		// First block must have _type.
		return isset( $blocks[0]['_type'] );
	}

	/**
	 * Extract plaintext from a Portable Text document.
	 *
	 * Walks all blocks and concatenates span text.
	 *
	 * @param array<int,array<string,mixed>> $blocks PT blocks.
	 * @return string
	 */
	public function extract_plaintext( array $blocks ): string {
		$parts = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( 'block' === ( $block['_type'] ?? '' ) && ! empty( $block['children'] ) ) {
				$spans = [];
				foreach ( $block['children'] as $child ) {
					if ( 'span' === ( $child['_type'] ?? '' ) && isset( $child['text'] ) ) {
						$spans[] = $child['text'];
					}
				}
				if ( $spans ) {
					$parts[] = implode( '', $spans );
				}
			}
		}

		return implode( "\n\n", $parts );
	}
}
