<?php
/**
 * Add image blocks to existing pages.
 *
 * Injects 1-2 image blocks into each page's Portable Text JSON.
 * Uses picsum.photos placeholder images with varied dimensions.
 *
 * Usage: wp eval-file scripts/add-images-to-pages.php --url=http://plugins.local/subsite23
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get all published pages.
$pages = $wpdb->get_results(
	"SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' ORDER BY ID ASC"
);

if ( empty( $pages ) ) {
	WP_CLI::error( 'No pages found.' );
}

$image_subjects = [
	'architecture',
	'nature',
	'technology',
	'business',
	'abstract',
	'city',
	'landscape',
	'workspace',
	'minimal',
	'ocean',
	'mountains',
	'forest',
	'desert',
	'office',
	'design',
	'texture',
	'pattern',
	'aerial',
	'sunset',
	'bridge',
];

$captions = [
	'An overview of the key concepts discussed in this guide.',
	'Visual representation of the workflow described above.',
	'Example output from the implementation.',
	'Architecture diagram showing component relationships.',
	'The recommended project structure for this approach.',
	'Performance comparison between different methods.',
	'Screenshot demonstrating the final result.',
	'Illustration of the data flow pattern.',
	'Reference diagram for the API endpoints.',
	'Overview of the system components.',
];

$updated = 0;
$skipped = 0;

foreach ( $pages as $page ) {
	$blocks = json_decode( $page->post_content, true );

	if ( ! is_array( $blocks ) || empty( $blocks ) || ! isset( $blocks[0]['_type'] ) ) {
		WP_CLI::warning( "Page {$page->ID} ({$page->post_title}): not PT content, skipping." );
		$skipped++;
		continue;
	}

	// Check if page already has images.
	$has_image = false;
	foreach ( $blocks as $block ) {
		if ( ( $block['_type'] ?? '' ) === 'image' ) {
			$has_image = true;
			break;
		}
	}
	if ( $has_image ) {
		WP_CLI::log( "Page {$page->ID} ({$page->post_title}): already has images, skipping." );
		$skipped++;
		continue;
	}

	$idx       = $page->ID % count( $image_subjects );
	$subject   = $image_subjects[ $idx ];
	$caption   = $captions[ $idx % count( $captions ) ];
	$seed      = $page->ID;
	$width     = ( $page->ID % 2 === 0 ) ? 800 : 960;
	$height    = ( $page->ID % 2 === 0 ) ? 450 : 540;

	// First image: after the intro paragraph (position 2, after h2 + first paragraph).
	$image1 = [
		'_type'   => 'image',
		'_key'    => 'img' . $seed . 'a',
		'src'     => "https://picsum.photos/seed/{$subject}{$seed}/{$width}/{$height}",
		'alt'     => ucfirst( $subject ) . ' — illustration for ' . $page->post_title,
		'caption' => $caption,
	];

	// Second image: different dimensions, placed later.
	$idx2      = ( $page->ID + 7 ) % count( $image_subjects );
	$subject2  = $image_subjects[ $idx2 ];
	$caption2  = $captions[ $idx2 % count( $captions ) ];

	$image2 = [
		'_type'   => 'image',
		'_key'    => 'img' . $seed . 'b',
		'src'     => "https://picsum.photos/seed/{$subject2}{$seed}/720/480",
		'alt'     => ucfirst( $subject2 ) . ' — related visual for ' . $page->post_title,
		'caption' => $caption2,
	];

	// Insert image1 after position 2 (after h2 + intro paragraph).
	$insert_pos1 = min( 2, count( $blocks ) );
	array_splice( $blocks, $insert_pos1, 0, [ $image1 ] );

	// Insert image2 before the last 2 blocks (before hr + closing paragraph).
	$insert_pos2 = max( $insert_pos1 + 2, count( $blocks ) - 2 );
	array_splice( $blocks, $insert_pos2, 0, [ $image2 ] );

	$json = wp_json_encode( $blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	$result = $wpdb->update(
		$wpdb->posts,
		[ 'post_content' => $json ],
		[ 'ID' => $page->ID ],
		[ '%s' ],
		[ '%d' ]
	);

	if ( false === $result ) {
		WP_CLI::warning( "Page {$page->ID}: DB update failed." );
	} else {
		clean_post_cache( $page->ID );
		$updated++;
		WP_CLI::log( "Page {$page->ID} ({$page->post_title}): added 2 images." );
	}
}

WP_CLI::success( "Done. Updated: {$updated}, Skipped: {$skipped}." );
