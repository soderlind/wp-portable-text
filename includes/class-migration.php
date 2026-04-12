<?php
/**
 * WP-CLI migration command — Gutenberg/HTML → Portable Text.
 *
 * @package WPPortableText
 */

declare(strict_types=1);

namespace WPPortableText;

/**
 * Registers the `wp portable-text migrate` WP-CLI command.
 */
class Migration {

	/**
	 * Register WP-CLI command.
	 */
	public function register(): void {
		\WP_CLI::add_command( 'portable-text migrate', [ $this, 'migrate' ] );
	}

	/**
	 * Migrate post content from Gutenberg/HTML to Portable Text JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<type>]
	 * : Post type to migrate. Default 'post'.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated post IDs to migrate.
	 *
	 * [--limit=<number>]
	 * : Maximum posts to process. Default all.
	 *
	 * [--dry-run]
	 * : Preview changes without saving.
	 *
	 * [--node-path=<path>]
	 * : Path to Node.js binary. Default 'node'.
	 *
	 * ## EXAMPLES
	 *
	 *     wp portable-text migrate --dry-run --limit=10
	 *     wp portable-text migrate --post-type=page --ids=1,2,3
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function migrate( array $args, array $assoc_args ): void {
		$post_type = $assoc_args[ 'post-type' ] ?? 'post';
		$dry_run   = isset( $assoc_args[ 'dry-run' ] );
		$node_path = $assoc_args[ 'node-path' ] ?? 'node';
		$limit     = isset( $assoc_args[ 'limit' ] ) ? (int) $assoc_args[ 'limit' ] : -1;

		// Verify the conversion script exists.
		$script_path = PLUGIN_DIR . '/scripts/convert.mjs';
		if ( ! file_exists( $script_path ) ) {
			\WP_CLI::error( 'Conversion script not found: ' . $script_path );
			return;
		}

		// Verify Node.js is available.
		$node_version = shell_exec( escapeshellarg( $node_path ) . ' --version 2>&1' );
		if ( ! $node_version || ! str_starts_with( trim( (string) $node_version ), 'v' ) ) {
			\WP_CLI::error( 'Node.js not found at: ' . $node_path );
			return;
		}
		\WP_CLI::log( 'Using Node.js ' . trim( (string) $node_version ) );

		// Build query.
		$query_args = [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => $limit > 0 ? $limit : 100,
			'no_found_rows'  => false,
			'fields'         => 'ids',
		];

		if ( ! empty( $assoc_args[ 'ids' ] ) ) {
			$query_args[ 'post__in' ] = array_map( 'intval', explode( ',', $assoc_args[ 'ids' ] ) );
		}

		$paged   = 1;
		$success = 0;
		$skipped = 0;
		$failed  = 0;
		$total   = 0;

		do {
			$query_args[ 'paged' ] = $paged;
			$query               = new \WP_Query( $query_args );
			$post_ids            = $query->posts;

			if ( empty( $post_ids ) ) {
				break;
			}

			$found = $query->found_posts;
			if ( 1 === $paged ) {
				$count = $limit > 0 ? min( $limit, $found ) : $found;
				\WP_CLI::log( sprintf( 'Found %d posts to migrate.', $count ) );
			}

			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				++$total;

				if ( $limit > 0 && $total > $limit ) {
					break 2;
				}

				$content = $post->post_content;

				// Skip if already PT JSON.
				$decoded = json_decode( $content, true );
				if ( is_array( $decoded ) && isset( $decoded[ 0 ][ '_type' ] ) ) {
					\WP_CLI::log( sprintf( '  [skip] Post %d: already Portable Text.', $post_id ) );
					++$skipped;
					continue;
				}

				// Convert via Node.js script.
				$result = $this->convert_via_node( $content, $node_path, $script_path );

				if ( null === $result ) {
					\WP_CLI::warning( sprintf( '  [fail] Post %d: conversion failed.', $post_id ) );
					++$failed;
					continue;
				}

				if ( $dry_run ) {
					\WP_CLI::log( sprintf( '  [dry-run] Post %d: would convert (%d blocks).', $post_id, count( $result ) ) );
					++$success;
					continue;
				}

				// Save converted content.
				$json    = wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$updated = wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => $json,
					],
					true
				);

				if ( is_wp_error( $updated ) ) {
					\WP_CLI::warning( sprintf( '  [fail] Post %d: %s', $post_id, $updated->get_error_message() ) );
					++$failed;
				} else {
					\WP_CLI::log( sprintf( '  [ok]   Post %d: converted (%d blocks).', $post_id, count( $result ) ) );
					++$success;
				}
			}

			++$paged;
		} while ( count( $post_ids ) === $query_args[ 'posts_per_page' ] );

		$mode = $dry_run ? ' (dry run)' : '';
		\WP_CLI::success( sprintf( 'Done%s. Success: %d, Skipped: %d, Failed: %d.', $mode, $success, $skipped, $failed ) );
	}

	/**
	 * Convert HTML content to PT JSON via Node.js.
	 *
	 * @param string $html        HTML content to convert.
	 * @param string $node_path   Path to Node.js binary.
	 * @param string $script_path Path to conversion script.
	 * @return array<int,array<string,mixed>>|null PT blocks or null on failure.
	 */
	private function convert_via_node( string $html, string $node_path, string $script_path ): ?array {
		// Write HTML to a temp file to avoid shell escaping issues.
		$tmp_file = tempnam( sys_get_temp_dir(), 'wp_pt_' );
		if ( false === $tmp_file ) {
			return null;
		}

		file_put_contents( $tmp_file, $html );

		$cmd    = sprintf(
			'%s %s --input %s 2>&1',
			escapeshellarg( $node_path ),
			escapeshellarg( $script_path ),
			escapeshellarg( $tmp_file )
		);
		$output = shell_exec( $cmd );

		unlink( $tmp_file );

		if ( null === $output ) {
			return null;
		}

		$decoded = json_decode( trim( $output ), true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}
}
