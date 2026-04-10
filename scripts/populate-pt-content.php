<?php
/**
 * Populate the site with 50 posts and 50 pages containing Portable Text JSON.
 *
 * Usage: wp eval-file populate-pt-content.php --url=http://plugins.local/subsite23
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Remove kses filters that corrupt JSON content.
remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
remove_filter( 'content_filtered_save_pre', 'wp_filter_post_kses' );

/**
 * Generate a Portable Text document with varied, realistic content.
 */
function generate_pt_content( int $index, string $type ): string {
	$topics = [
		'Getting Started with WordPress',
		'Modern Web Development Practices',
		'Building Scalable Applications',
		'Understanding REST APIs',
		'Introduction to Portable Text',
		'The Future of Content Management',
		'Optimizing Database Performance',
		'Secure Coding Practices',
		'Responsive Design Patterns',
		'JavaScript Framework Comparison',
		'PHP Best Practices in 2026',
		'Container Orchestration Guide',
		'API Authentication Methods',
		'Web Accessibility Standards',
		'Performance Monitoring Tools',
		'DevOps Workflow Automation',
		'Cloud Architecture Patterns',
		'Data Migration Strategies',
		'Frontend Build Tooling',
		'Backend Service Design',
		'User Experience Principles',
		'Content Strategy Planning',
		'Search Engine Optimization',
		'Caching Strategies Explained',
		'Plugin Development Workflow',
		'Theme Customization Guide',
		'Block Editor Alternatives',
		'Headless CMS Architecture',
		'Static Site Generation',
		'Serverless Computing Overview',
		'GraphQL vs REST Comparison',
		'Testing Strategies for WordPress',
		'Continuous Integration Setup',
		'Monitoring and Observability',
		'Error Handling Patterns',
		'Internationalization Guide',
		'Multi-site WordPress Setup',
		'Custom Post Types Deep Dive',
		'Taxonomy and Term Management',
		'Media Library Optimization',
		'Form Handling Best Practices',
		'Email Integration Patterns',
		'Payment Gateway Integration',
		'Real-time Data with WebSockets',
		'Browser Storage Mechanisms',
		'Service Worker Implementation',
		'Image Optimization Techniques',
		'Video Embedding Standards',
		'Code Review Best Practices',
		'Documentation Writing Guide',
	];

	$topic = $topics[ $index % count( $topics ) ];

	$paragraphs = [
		"This comprehensive guide covers the essential concepts and practical techniques you need to master. Whether you're a beginner or an experienced developer, you'll find valuable insights here.",
		"Understanding the fundamentals is crucial before diving into advanced topics. Let's start with the basics and progressively build our knowledge.",
		"In modern development, choosing the right tools and patterns can make a significant difference in productivity and code quality.",
		"Performance optimization should be considered from the start, not as an afterthought. Small decisions early on compound into major differences.",
		"Security is not optional. Every application should implement proper input validation, output escaping, and authentication mechanisms.",
		"Testing gives you confidence to refactor and extend your code. A well-tested codebase is easier to maintain and less prone to regressions.",
		"Documentation is a gift to your future self and your team. Clear, concise documentation saves hours of debugging and onboarding time.",
		"The ecosystem continues to evolve rapidly. Staying current with best practices ensures your projects remain maintainable and secure.",
	];

	$code_samples = [
		[ 'php', '<?php\n\nfunction get_custom_data( int $id ): array {\n    $cache_key = "custom_data_{$id}";\n    $cached = wp_cache_get( $cache_key );\n\n    if ( false !== $cached ) {\n        return $cached;\n    }\n\n    $data = get_post_meta( $id, \'_custom_data\', true );\n    wp_cache_set( $cache_key, $data, \'\', 3600 );\n\n    return $data ?: [];\n}' ],
		[ 'javascript', 'async function fetchPosts(page = 1) {\n  const response = await fetch(\n    `/wp-json/wp/v2/posts?_fields=id,title,portable_text&page=${page}`\n  );\n  const posts = await response.json();\n  const total = response.headers.get(\'X-WP-Total\');\n\n  return { posts, total: parseInt(total, 10) };\n}' ],
		[ 'typescript', 'interface PortableTextBlock {\n  _type: string;\n  _key: string;\n  style?: string;\n  children?: PortableTextChild[];\n  markDefs?: MarkDef[];\n}\n\nfunction renderBlock(block: PortableTextBlock): string {\n  if (block._type === \'image\') {\n    return `<img src="${block.src}" alt="${block.alt}" />`;\n  }\n  return `<p>${block.children?.map(c => c.text).join(\'\')}</p>`;\n}' ],
		[ 'bash', '#!/bin/bash\n\n# Deploy WordPress plugin\nVERSION=$(jq -r .version package.json)\nBUILD_DIR="build/wp-portable-text"\n\nnpm run build\nmkdir -p "$BUILD_DIR"\ncp -r includes/ "$BUILD_DIR/"\ncp -r build/editor/ "$BUILD_DIR/build/editor/"\ncp wp-portable-text.php "$BUILD_DIR/"\n\ncd build && zip -r "wp-portable-text-${VERSION}.zip" wp-portable-text/' ],
		[ 'css', '.wp-portable-text-editor {\n  display: flex;\n  flex-direction: column;\n  gap: 1rem;\n  max-width: 800px;\n  margin: 0 auto;\n  font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;\n}\n\n.wp-portable-text-editor .block {\n  padding: 0.5rem 1rem;\n  border-left: 3px solid transparent;\n  transition: border-color 0.2s;\n}\n\n.wp-portable-text-editor .block:focus-within {\n  border-left-color: #007cba;\n}' ],
	];

	$key_counter = 0;
	$make_key = function() use ( &$key_counter ) {
		return 'k' . str_pad( (string) ( ++$key_counter ), 4, '0', STR_PAD_LEFT );
	};

	$blocks = [];

	// H2 heading.
	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'h2',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => $topic, 'marks' => [] ],
		],
		'markDefs' => [],
	];

	// Intro paragraph with bold text.
	$intro = $paragraphs[ $index % count( $paragraphs ) ];
	$words = explode( ' ', $intro );
	$bold_start = (int) floor( count( $words ) * 0.1 );
	$bold_end   = $bold_start + 3;
	$before     = implode( ' ', array_slice( $words, 0, $bold_start ) ) . ' ';
	$bold       = implode( ' ', array_slice( $words, $bold_start, 3 ) );
	$after      = ' ' . implode( ' ', array_slice( $words, $bold_end ) );

	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'normal',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => $before, 'marks' => [] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => $bold, 'marks' => [ 'strong' ] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => $after, 'marks' => [] ],
		],
		'markDefs' => [],
	];

	// H3 sub-heading.
	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'h3',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => 'Key Concepts', 'marks' => [] ],
		],
		'markDefs' => [],
	];

	// Paragraph with a link.
	$link_key = $make_key();
	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'normal',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => 'For more details, check the ', 'marks' => [] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => 'official documentation', 'marks' => [ $link_key ] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => ' which covers all the edge cases and advanced usage patterns.', 'marks' => [] ],
		],
		'markDefs' => [
			[ '_type' => 'link', '_key' => $link_key, 'href' => 'https://developer.wordpress.org/' ],
		],
	];

	// Bullet list (3 items).
	$list_items = [
		'Start with a solid foundation and clear architecture',
		'Implement proper error handling and validation',
		'Write tests to ensure reliability and prevent regressions',
	];
	foreach ( $list_items as $item_text ) {
		$blocks[] = [
			'_type'    => 'block',
			'_key'     => $make_key(),
			'style'    => 'normal',
			'listItem' => 'bullet',
			'level'    => 1,
			'children' => [
				[ '_type' => 'span', '_key' => $make_key(), 'text' => $item_text, 'marks' => [] ],
			],
			'markDefs' => [],
		];
	}

	// Another paragraph.
	$p2 = $paragraphs[ ( $index + 3 ) % count( $paragraphs ) ];
	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'normal',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => $p2, 'marks' => [] ],
		],
		'markDefs' => [],
	];

	// Code block (varied by index).
	$code_index = $index % count( $code_samples );
	$blocks[] = [
		'_type'    => 'codeBlock',
		'_key'     => $make_key(),
		'code'     => $code_samples[ $code_index ][1],
		'language' => $code_samples[ $code_index ][0],
	];

	// Blockquote.
	$quotes = [
		'Any sufficiently advanced technology is indistinguishable from magic.',
		'Premature optimization is the root of all evil.',
		'Make it work, make it right, make it fast — in that order.',
		'Code is read much more often than it is written.',
		'The best code is no code at all.',
	];
	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'blockquote',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => $quotes[ $index % count( $quotes ) ], 'marks' => [ 'em' ] ],
		],
		'markDefs' => [],
	];

	// Closing paragraph with italic and code spans.
	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'normal',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => 'Remember to always test your changes using ', 'marks' => [] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => 'phpunit', 'marks' => [ 'code' ] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => ' and review the ', 'marks' => [] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => 'changelog', 'marks' => [ 'em' ] ],
			[ '_type' => 'span', '_key' => $make_key(), 'text' => ' before deploying.', 'marks' => [] ],
		],
		'markDefs' => [],
	];

	// Horizontal rule.
	$blocks[] = [
		'_type' => 'break',
		'_key'  => $make_key(),
	];

	// Final paragraph.
	$blocks[] = [
		'_type'    => 'block',
		'_key'     => $make_key(),
		'style'    => 'normal',
		'children' => [
			[ '_type' => 'span', '_key' => $make_key(), 'text' => "That wraps up our guide on {$topic}. Stay tuned for more content.", 'marks' => [] ],
		],
		'markDefs' => [],
	];

	return wp_json_encode( $blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

$titles_posts = [
	'Getting Started with WordPress Development',
	'Modern CSS Grid Layout Techniques',
	'Building REST APIs with PHP',
	'React 19: What\'s New and Why It Matters',
	'Database Query Optimization Guide',
	'Securing Your WordPress Installation',
	'Introduction to Portable Text Format',
	'Custom Post Types: A Complete Guide',
	'WordPress Hook System Explained',
	'JavaScript Build Tools Comparison',
	'Understanding WordPress Nonces',
	'Implementing Caching Strategies',
	'WordPress Multisite Architecture',
	'Responsive Typography Systems',
	'Plugin Architecture Best Practices',
	'WordPress REST API Authentication',
	'Performance Profiling with Query Monitor',
	'Schema Validation in PHP',
	'Managing WordPress Taxonomies',
	'Frontend State Management Patterns',
	'WordPress Block Registration Guide',
	'Server-Side Rendering in WordPress',
	'WordPress Cron Job Management',
	'Building Custom Admin Pages',
	'Content Migration Strategies',
	'WordPress Object Caching Deep Dive',
	'Modern PHP Features for WordPress',
	'WordPress Template Hierarchy',
	'Building WordPress Widgets',
	'WP-CLI Command Development',
	'WordPress Rewrite API Guide',
	'Transient API Best Practices',
	'WordPress AJAX Handling',
	'Custom Database Tables in WP',
	'WordPress Internationalization',
	'Email Templating in WordPress',
	'WordPress User Roles and Capabilities',
	'Building a WordPress REST Client',
	'WordPress Debug Logging Techniques',
	'Plugin Dependency Management',
	'WordPress Action Scheduler Guide',
	'Building Settings Pages with React',
	'WordPress Media Handling API',
	'Content Sanitization Patterns',
	'WordPress Application Architecture',
	'Headless WordPress with Next.js',
	'WordPress Performance Checklist',
	'Building a Custom Block Editor',
	'WordPress CI/CD Pipeline Setup',
	'WordPress Plugin Testing Guide',
];

$titles_pages = [
	'About Our Development Team',
	'Documentation Hub',
	'API Reference Overview',
	'Getting Started Guide',
	'Contributing Guidelines',
	'Code of Conduct',
	'Plugin Features',
	'System Requirements',
	'Installation Instructions',
	'Frequently Asked Questions',
	'Troubleshooting Guide',
	'Release Notes Archive',
	'Roadmap and Future Plans',
	'Security Policy',
	'Privacy Policy',
	'Terms of Service',
	'Support and Contact',
	'Developer Resources',
	'Architecture Overview',
	'Migration Guide',
	'Configuration Reference',
	'Theme Compatibility',
	'Performance Tuning',
	'Accessibility Standards',
	'Browser Support Matrix',
	'Changelog Archive',
	'License Information',
	'Third-Party Credits',
	'Data Handling Policy',
	'Plugin Comparison Chart',
	'Integration Partners',
	'Community Guidelines',
	'Bug Reporting Process',
	'Feature Request Process',
	'Beta Testing Program',
	'Localization Status',
	'Upgrade Instructions',
	'Backup and Recovery',
	'Server Requirements',
	'Hosting Recommendations',
	'Glossary of Terms',
	'Quick Start Tutorial',
	'Advanced Configuration',
	'Webhook Documentation',
	'Error Code Reference',
	'Status Page',
	'Service Level Agreement',
	'Compliance Information',
	'Partner Program',
	'Press Kit and Resources',
];

$created_posts = 0;
$created_pages = 0;

// Create 50 posts.
WP_CLI::log( 'Creating 50 posts with Portable Text content...' );
for ( $i = 0; $i < 50; $i++ ) {
	$title   = $titles_posts[ $i ];
	$content = generate_pt_content( $i, 'post' );

	$post_id = wp_insert_post( [
		'post_title'   => $title,
		'post_content' => wp_slash( $content ),
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_author'  => 1,
	], true );

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( "Failed to create post '{$title}': " . $post_id->get_error_message() );
		continue;
	}

	// Directly update post_content to ensure JSON is preserved (bypass kses).
	global $wpdb;
	$wpdb->update(
		$wpdb->posts,
		[ 'post_content' => $content ],
		[ 'ID' => $post_id ],
		[ '%s' ],
		[ '%d' ]
	);

	// Also populate post_content_filtered with plaintext.
	$decoded   = json_decode( $content, true );
	$plaintext = '';
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $block ) {
			if ( isset( $block['children'] ) ) {
				foreach ( $block['children'] as $child ) {
					if ( ! empty( $child['text'] ) ) {
						$plaintext .= $child['text'] . ' ';
					}
				}
			}
		}
	}
	$wpdb->update(
		$wpdb->posts,
		[ 'post_content_filtered' => trim( $plaintext ) ],
		[ 'ID' => $post_id ],
		[ '%s' ],
		[ '%d' ]
	);

	$created_posts++;
	if ( ( $i + 1 ) % 10 === 0 ) {
		WP_CLI::log( "  ... created {$created_posts} posts" );
	}
}

// Create 50 pages.
WP_CLI::log( 'Creating 50 pages with Portable Text content...' );
for ( $i = 0; $i < 50; $i++ ) {
	$title   = $titles_pages[ $i ];
	$content = generate_pt_content( $i + 50, 'page' );

	$page_id = wp_insert_post( [
		'post_title'   => $title,
		'post_content' => wp_slash( $content ),
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => 1,
	], true );

	if ( is_wp_error( $page_id ) ) {
		WP_CLI::warning( "Failed to create page '{$title}': " . $page_id->get_error_message() );
		continue;
	}

	// Directly update post_content to ensure JSON is preserved.
	global $wpdb;
	$wpdb->update(
		$wpdb->posts,
		[ 'post_content' => $content ],
		[ 'ID' => $page_id ],
		[ '%s' ],
		[ '%d' ]
	);

	// Also populate post_content_filtered with plaintext.
	$decoded   = json_decode( $content, true );
	$plaintext = '';
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $block ) {
			if ( isset( $block['children'] ) ) {
				foreach ( $block['children'] as $child ) {
					if ( ! empty( $child['text'] ) ) {
						$plaintext .= $child['text'] . ' ';
					}
				}
			}
		}
	}
	$wpdb->update(
		$wpdb->posts,
		[ 'post_content_filtered' => trim( $plaintext ) ],
		[ 'ID' => $page_id ],
		[ '%s' ],
		[ '%d' ]
	);

	$created_pages++;
	if ( ( $i + 1 ) % 10 === 0 ) {
		WP_CLI::log( "  ... created {$created_pages} pages" );
	}
}

clean_post_cache( 0 );

WP_CLI::success( "Created {$created_posts} posts and {$created_pages} pages with Portable Text content." );
