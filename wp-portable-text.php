<?php
/**
 * Plugin Name: WP Portable Text
 * Plugin URI:  https://github.com/soderlind/wp-portable-text
 * Description: Replaces the Gutenberg block editor with a Portable Text editor. Stores content as structured JSON, renders via PHP.
 * Version:     0.1.9
 * Author:      Per Soderlind
 * Author URI:  https://developer.suspended.dev
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-portable-text
 * Requires at least: 7.0
 * Requires PHP: 8.3
 */

declare(strict_types=1);

namespace WPPortableText;

defined( 'ABSPATH' ) || exit;

// Prefer Composer autoloader; fall back to a simple PSR-4 loader
// so the plugin works without `composer install` (e.g. release zips).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register( static function ( string $class ): void {
		$prefix = 'WPPortableText\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = __DIR__ . '/includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

const VERSION    = '0.1.9';
const PLUGIN_DIR = __DIR__;
const PLUGIN_URL = null; // Resolved at runtime via plugin_dir_url().

/**
 * Return the plugin URL (cached).
 */
function plugin_url(): string {
	static $url;
	if ( null === $url ) {
		$url = plugin_dir_url( __FILE__ );
	}
	return $url;
}

/**
 * Bootstrap the plugin on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		// Phase 1: Replace the block editor.
		$editor = new Editor();
		$editor->register();

		// Phase 1: Content filtering (kses bypass for JSON).
		$content_filter = new Content_Filter();
		$content_filter->register();

		// Phase 2: Frontend rendering (PT JSON → HTML).
		$renderer = new Renderer();
		$renderer->register();

		// Phase 2: Content query API (GROQ-like).
		$query = new Query();
		$query->register();

		// Phase 3: Migration CLI command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$migration = new Migration();
			$migration->register();
		}
	}
);
