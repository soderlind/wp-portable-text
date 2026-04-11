# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.12] - 2026-04-11

### Added

- `table` block type to Query API `BLOCK_TYPES` enum — tables are now queryable via `/query` and `/blocks` endpoints.
- `X-WP-PT-Cache` header documented in `docs/QUERY.md`.
- `table` block type added to schema reference in `docs/REST.md`.

### Fixed

- `docs/REST.md`: corrected "every public post type" to "post types with `show_in_rest` enabled".

## [0.1.11] - 2026-04-11

### Changed

- Require PHP 8.3+ (was 7.4 in plugin header, 8.2 in Composer).
- Modernize PHP to 8.3 idioms:
  - `strpos() === 0` → `str_starts_with()` across 4 files.
  - `array_keys() === range()` → `array_is_list()` in Content_Filter and Renderer.
  - `switch`/`return` → `match` expressions in Renderer (`render_block`, `render_text_block`, `apply_decorator`).
  - Typed class constants (PHP 8.3) in Query and Editor classes.

### Fixed

- `VERSION` constant in `wp-portable-text.php` was stuck at `0.1.4`; now `0.1.9`.
- README: corrected hook name (`replace_editor` → `use_block_editor_for_post` + `edit_form_after_title`), REST field name (`rendered_content` → `portable_text`), and post-type scope wording.

## [0.1.10] - 2026-04-11

### Changed

- Bump `@wordpress/scripts` from ^30 to ^31 (31.8.0).
- Bump `typescript` from ^5 to ^6 (6.0.2).
- Bump `phpunit/phpunit` from ^9.6 to ^12 (12.5.17); minimum PHP raised to 8.2.
- Update `phpunit.xml` schema from 9.6 to 12.5 (`<coverage>` → `<source>`).

## [0.1.9] - 2026-04-10

### Added

- Query API at `/wp-json/wp-portable-text/v1/` with two endpoints:
  - `/query` — find posts by block type, style, or mark/annotation presence.
  - `/blocks` — extract specific blocks across posts (e.g., all images, all code blocks filtered by language).
- REST API write support: `portable_text` field is now read-write; create/update posts with PT JSON via the REST API.
- Transient-based query cache with `X-WP-PT-Cache: HIT|MISS` header; 5-minute TTL (filterable via `wp_portable_text_query_cache_ttl`); auto-invalidates on `save_post`, `delete_post`, `wp_trash_post`.
- Query API documented in `docs/QUERY.md`; REST write examples added to `docs/REST.md`.
- 15 new PHPUnit tests for the Query class (72 total).

### Fixed

- Query API `enum` and `maximum` constraints now enforced via `validate_callback` (returns 400 for invalid `block_type` or out-of-range `per_page`).

## [0.1.8] - 2026-04-10

### Added

- Revision diffs now display rendered HTML instead of raw JSON via `_wp_post_revision_field_post_content` filter. JSON remains stored in `post_content`.
- Editor area is now vertically resizable (CSS `resize: vertical`), matching the classic WP editor.

### Changed

- Moved JSON / HTML / MD preview tabs above the editor container, visually attached to the toolbar.
- Preview tabs are right-aligned.
- Added REST API documentation (`docs/REST.md`) with examples and schema reference; linked from README.

## [0.1.7] - 2026-04-10

### Fixed

- Excluded internal WP post types (`wp_navigation`, `wp_template`, `wp_template_part`, `wp_global_styles`, `wp_font_face`, `wp_font_family`, `wp_block`) from block editor disable and editor-support removal, fixing `array_merge()` fatal in `WP_Navigation_Fallback`.
- Added null/type guards in REST `portable_text` field callback and `trim_excerpt()` to prevent `json_decode()` TypeError on post types with null `post_content` (e.g. `wp_template`).

### Added

- PHPUnit test suite (53 tests) with Brain Monkey for WP mocking. Covers `Content_Filter`, `Editor`, and `Renderer` classes including security checks (XSS, nonce, capabilities).

## [0.1.6] - 2026-04-10

### Fixed

- Code block insertion now uses `editor.send('insert.block object')` directly instead of `useBlockObjectButton`, fixing the same focus-loss issue that previously affected image insertion.

### Added

- Click-to-edit on code blocks: clicking a code block opens a dialog pre-populated with the current code and language. Uses `block.set` to patch values in place.

## [0.1.5] - 2026-04-09

### Fixed

- Block objects (image, break, codeBlock, embed) now render correctly in the editor. `renderBlockObject` was not a valid prop on `PortableTextEditable`; merged all block object rendering into `renderBlock`.

### Added

- Click-to-edit on image blocks: clicking an image opens a dialog with alt text, caption fields, and a "Replace Image" button that opens the WP media modal. Uses `block.set` to patch values in place.

## [0.1.4] - 2026-04-09

### Fixed

- Image insertion now uses `editor.send('insert.block object')` directly instead of `useBlockObjectButton`, fixing the issue where the WP media modal caused the editor to lose focus and the toolbar state machine to go to 'disabled' before the select callback could fire.

## [0.1.3] - 2026-04-09

### Changed

- Image button now opens the WordPress media library modal instead of plain input fields.
- Code Block button now opens a dialog with a code textarea (dark theme, Tab support, monospace font) and a language dropdown (27 languages) instead of two text inputs.

## [0.1.2] - 2026-04-09

### Changed

- Moved Slug and Author metaboxes from below the editor to the sidebar.

### Removed

- Custom Fields metabox (not needed — PT stores structured JSON in post_content).

## [0.1.1] - 2026-04-09

### Added

- Preview panel below editor with toggle buttons for JSON, HTML, and Markdown views.
- Client-side PT-to-HTML and PT-to-Markdown serializers (`serializers.ts`).

## [0.1.0] - 2026-04-09

### Added

- Initial plugin scaffold: bootstrap, autoloader, and hook registration.
- Replace Gutenberg block editor with Portable Text editor for all post types.
- PT schema with decorators (strong, em, underline, strike-through, code, subscript, superscript), styles (normal, h1–h6, blockquote), annotations (link), lists (bullet, number), and block objects (break, image, codeBlock, embed).
- Toolbar with style dropdown, decorator buttons, link annotation button with URL dialog, annotation popover for editing/removing links, list buttons, and block object insertion buttons.
- Keyboard shortcuts for bold, italic, underline, strikethrough, code, headings, blockquote, and link.
- Editor render functions for annotations (link with dashed underline), block objects (hr, image preview, code block, embed placeholder), and all decorators.
- Content filter: bypass kses/balanceTags for PT JSON on save, populate `post_content_filtered` with plaintext for search.
- PHP renderer: `the_content` filter converts PT JSON to HTML, extensible via filters (`wp_portable_text_render_block`, `wp_portable_text_render_inline`, `wp_portable_text_render_annotation`). REST API field for rendered HTML.
- HTML-to-Portable-Text conversion using DOMDocument for existing content (headings, paragraphs, lists, images, figures, code blocks, inline marks, links).
- WP-CLI `wp portable-text migrate` command with `--dry-run`, `--post-type`, `--ids`, `--limit`, `--node-path` options.
- Custom webpack config bundling React 19 separately from WP's React 18 for PT editor compatibility.
