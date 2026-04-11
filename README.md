# WP Portable Text

```diff
- ALPHA - Not ready for production use. Expect bugs and breaking changes.
```

>You should review the [CHANGELOG](CHANGELOG.md) for recent changes and the [REST API docs](docs/REST.md) for details on the REST endpoints and JSON schema. [Query API](docs/QUERY.md) added in v0.1.9 allows you to find posts by block type, style, or annotation presence, and extract specific blocks across posts (e.g., all images, all code blocks filtered by language).

A WordPress plugin that replaces the Gutenberg block editor with a [Portable Text](https://www.portabletext.org/) editor. Content is stored as structured JSON in `post_content` and rendered to HTML via PHP on the front end.

## Why Portable Text?

Portable Text is a JSON-based rich text specification created by Sanity.io. Unlike Gutenberg's HTML comment delimiters, PT stores content as a clean, typed data structure — making it easy to render to any format (HTML, Markdown, RSS, email, native apps) and query programmatically.

It also makes it easy to [query content](docs/QUERY.md) by block type, style, or annotation presence, and to extract specific blocks across posts (e.g., all images, all code blocks filtered by language) — features that are difficult with Gutenberg's HTML-based storage.

## Features

- **Rich text editor** powered by `@portabletext/editor` with toolbar, keyboard shortcuts, and inline preview
- **Decorators:** bold, italic, underline, strikethrough, code, subscript, superscript
- **Styles:** paragraph, h1–h6, blockquote
- **Annotations:** links (with popover for editing/removing)
- **Lists:** bullet and numbered
- **Block objects:** separator (hr), image (via WP media library), code block (with language selector), embed
- **Click-to-edit** image blocks with alt text, caption, and replace support
- **Preview panel** below the editor with JSON, HTML, and Markdown toggle views
- **PHP renderer** converts PT JSON → HTML on `the_content`, with filters for customization
- **Plaintext search** — `post_content_filtered` is populated with a plain-text version for WordPress search
- **HTML → PT migration** for existing content using DOMDocument
- **WP-CLI command** `wp portable-text migrate` with `--dry-run`, `--post-type`, `--ids`, `--limit` options

## Requirements

- WordPress 7.0+
- PHP 8.3+
- Node.js 18+ (for building)

## Installation

```bash
cd wp-content/plugins/wp-portable-text
npm install
npm run build
```

Activate the plugin in **Plugins → Installed Plugins**.

## Development

```bash
npm run start    # Watch mode with hot reload
npm run build    # Production build
npm run lint:js  # Lint TypeScript/JavaScript
npm run lint:css # Lint CSS
```

## Project Structure

```
wp-portable-text.php          Plugin bootstrap and autoloader
includes/
  class-editor.php            Disables Gutenberg, mounts PT editor, enqueues assets
  class-content-filter.php    Bypasses kses for PT JSON, populates plaintext for search
  class-renderer.php          the_content filter: PT JSON → HTML
  class-migration.php         WP-CLI migrate command, HTML → PT conversion
src/editor/
  index.tsx                   React app entry, render functions for all PT elements
  schema.ts                   PT schema definition (decorators, styles, annotations, etc.)
  serializers.ts              Client-side PT → HTML and PT → Markdown serializers
  styles.css                  Editor and toolbar styles
  types.ts                    TypeScript type declarations
  components/
    Toolbar.tsx               Toolbar with style dropdown, decorators, annotations, lists, block objects
    PreviewPanel.tsx           JSON / HTML / Markdown preview toggle
```

## Schema

The editor's Portable Text schema is defined in `src/editor/schema.ts`:

| Type            | Values                                                              |
|-----------------|---------------------------------------------------------------------|
| **Decorators**  | strong, em, underline, strike-through, code, subscript, superscript |
| **Styles**      | normal, h1–h6, blockquote                                          |
| **Annotations** | link (href)                                                         |
| **Lists**       | bullet, number                                                      |
| **Block objects**| break, image (src, alt, caption, attachmentId), codeBlock (code, language), embed (url) |

## PHP Filters

Customize the HTML output via filters on the PHP renderer:

- `wp_portable_text_render_block` — Filter each block's HTML
- `wp_portable_text_render_inline` — Filter inline element HTML
- `wp_portable_text_render_annotation` — Filter annotation (e.g. link) HTML

## REST API

The plugin exposes a `portable_text` field on all post types with `show_in_rest` enabled via the WordPress REST API. See [docs/REST.md](docs/REST.md) for endpoints, examples, and schema reference.

## Query API

A GROQ-like query API lets you find posts by block type, style, or annotation, and extract specific blocks (e.g., all images, all PHP code blocks) across posts. See [docs/QUERY.md](docs/QUERY.md) for endpoints and examples.

## How It Works

1. The plugin disables the block editor via `use_block_editor_for_post` and injects the PT editor via `edit_form_after_title`
2. Content is saved as JSON in `post_content`; a plaintext version goes to `post_content_filtered`
3. On the front end, `the_content` filter deserializes the JSON and renders HTML using the PHP renderer
4. The REST API includes a `portable_text` field with the parsed PT blocks (and `content.rendered` has the HTML)

## Technical Notes

- Bundles React 19 separately from WordPress's React 18 (required by `@portabletext/editor` v6)
- Custom webpack config via `@wordpress/scripts` with `DependencyExtractionPlugin` returning `false` for React modules
- Image insertion uses `editor.send('insert.block object')` directly (not `useBlockObjectButton`) because the WP media modal causes the editor to lose focus

## License

GPL-2.0-or-later

