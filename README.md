# WP Portable Text

```diff
- ALPHA - Not ready for production use. Expect bugs and breaking changes.
```

>You should review the [CHANGELOG](CHANGELOG.md) for recent changes and the [REST API docs](docs/REST.md) for details on the REST endpoints and JSON schema. [Query API](docs/QUERY.md) added in v0.1.9 allows you to find posts by block type, style, or annotation presence, and extract specific blocks across posts (e.g., all images, all code blocks filtered by language).

A WordPress plugin that replaces the Gutenberg block editor with a [Portable Text](https://www.portabletext.org/) editor. Content is stored as structured JSON in `post_content` and rendered to HTML via PHP on the front end.

[<img src="https://img.shields.io/badge/Launch%20in-WordPress%20Playground-3858e9?logo=wordpress&logoColor=white" alt="Launch in WordPress Playground" height="28">](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/soderlind/wp-portable-text/main/blueprint.json)

## Why Portable Text?

Portable Text is a JSON-based rich text specification created by Sanity.io. Unlike Gutenberg's HTML comment delimiters, PT stores content as a clean, typed data structure — making it easy to render to any format (HTML, Markdown, RSS, email, native apps) and query programmatically.

It also makes it easy to [query content](docs/QUERY.md) by block type, style, or annotation presence, and to extract specific blocks across posts (e.g., all images, all code blocks filtered by language) — features that are difficult with Gutenberg's HTML-based storage.

## Features

- **Rich text editor** powered by `@portabletext/editor` with toolbar, keyboard shortcuts, and inline preview
- **Decorators:** bold, italic, underline, strikethrough, code, subscript, superscript
- **Styles:** paragraph, h1–h6, blockquote
- **Annotations:** links (with popover for editing/removing)
- **Lists:** bullet and numbered
- **Block objects:** separator (hr), image (via WP media library), code block (with language selector), embed, table
- **Click-to-edit** image blocks with alt text, caption, and replace support
- **Preview panel** below the editor with JSON, HTML, and Markdown toggle views
- **PHP renderer** converts PT JSON → HTML on `the_content`, with pluggable serializers and filters for customization
- **Markdown alternate** — `Accept: text/markdown` or `?format=markdown` serves content as Markdown for AI clients and tools
- **Plaintext search** — `post_content_filtered` is populated with a plain-text version for WordPress search
- **HTML → PT migration** for existing content using DOMDocument
- **WP-CLI command** `wp portable-text migrate` with `--dry-run`, `--post-type`, `--ids`, `--limit` options

## Requirements

- WordPress 7.0+
- PHP 8.3+
- Node.js 18+ (for building)

## Installation


1. Download [\`wp-portable-text.zip\`](https://github.com/soderlind/wp-portable-text/releases/latest/download/wp-portable-text.zip)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and activate


Or clone the repo and install dependencies:

```bash
cd wp-content/plugins/wp-portable-text
composer install
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
wp-portable-text.php          Plugin bootstrap, loads Composer autoloader
includes/                     PSR-4 root (WPPortableText\)
  Editor.php                  Disables Gutenberg, mounts PT editor, enqueues assets
  Content_Filter.php          Bypasses kses for PT JSON, populates plaintext for search
  Renderer.php                the_content filter: shared PT walker, markdown alternate
  Migration.php               WP-CLI migrate command, HTML → PT conversion
  Query.php                   GROQ-like REST query API for PT content
  Serializers/                PSR-4 sub-namespace (WPPortableText\Serializers\)
    Serializer.php            Interface — 13 format-specific output callbacks
    Html_Serializer.php       HTML output (escaping, tags, oEmbed, WP filters)
    Markdown_Serializer.php   Markdown output (inline syntax, fenced code, pipe tables)
src/editor/
  index.tsx                   React app entry, render functions for all PT elements
  schema.ts                   PT schema definition (decorators, styles, annotations, etc.)
  serializers.ts              Client-side PT → HTML and PT → Markdown serializers
  styles.css                  Editor and toolbar styles
  types.ts                    TypeScript type declarations
  components/
    Toolbar.tsx               Toolbar with style dropdown, decorators, annotations, lists, block objects
    PreviewPanel.tsx          JSON / HTML / Markdown preview toggle
```

## Schema

The editor's Portable Text schema is defined in `src/editor/schema.ts`:

| Type            | Values                                                              |
|-----------------|---------------------------------------------------------------------|
| **Decorators**  | strong, em, underline, strike-through, code, subscript, superscript |
| **Styles**      | normal, h1–h6, blockquote                                          |
| **Annotations** | link (href)                                                         |
| **Lists**       | bullet, number                                                      |
| **Block objects**| break, image (src, alt, caption, attachmentId), codeBlock (code, language), embed (url), table (rows, hasHeaderRow) |

## PHP Filters

Customize the HTML output via filters on the PHP renderer:

- `wp_portable_text_render_block` — Filter each block's HTML
- `wp_portable_text_render_inline` — Filter inline element HTML
- `wp_portable_text_render_annotation` — Filter annotation (e.g. link) HTML

## Markdown Alternate

Every page that contains Portable Text content advertises a markdown alternate via `<link rel="alternate" type="text/markdown">` in `wp_head`. AI clients and tools can request it:

```bash
# Via query parameter
curl http://example.com/my-post/?format=markdown

# Via content negotiation
curl -H "Accept: text/markdown" http://example.com/my-post/
```

Works on singular posts, the blog home page, and archive pages. Responses include `Vary: Accept` and `X-Robots-Tag: noindex` headers.

## REST API

The plugin exposes a `portable_text` field on all post types with `show_in_rest` enabled via the WordPress REST API. See [docs/REST.md](docs/REST.md) for endpoints, examples, and schema reference.

## Query API

A GROQ-like query API lets you find posts by block type, style, or annotation, and extract specific blocks (e.g., all images, all PHP code blocks) across posts. See [docs/QUERY.md](docs/QUERY.md) for endpoints and examples.

## How It Works

1. The plugin disables the block editor via `use_block_editor_for_post` and injects the PT editor via `edit_form_after_title`
2. Content is saved as JSON in `post_content`; a plaintext version goes to `post_content_filtered`
3. On the front end, `the_content` filter deserializes the JSON and renders HTML via a shared PT walker that delegates to pluggable serializers (`Html_Serializer`, `Markdown_Serializer`)
4. The REST API includes a `portable_text` field with the parsed PT blocks (and `content.rendered` has the HTML)
5. AI clients can request markdown via `Accept: text/markdown` or `?format=markdown` on any page with PT content

## Technical Notes

- Bundles React 19 separately from WordPress's React 18 (required by `@portabletext/editor` v6)
- Custom webpack config via `@wordpress/scripts` with `DependencyExtractionPlugin` returning `false` for React modules
- Image insertion uses `editor.send('insert.block object')` directly (not `useBlockObjectButton`) because the WP media modal causes the editor to lose focus

## License

GPL-2.0-or-later

## AI Contribution Attribution

When AI tools contribute to development, proper attribution
helps track the evolving role of AI in the development process.
Contributions should include an Assisted-by tag in the following format:

`Assisted-by: AGENT_NAME:MODEL_VERSION [TOOL1] [TOOL2]`

Where:

* `AGENT_NAME` is the name of the AI tool or framework
* `MODEL_VERSION` is the specific model version used
* `[TOOL1] [TOOL2]` are optional specialized analysis tools used
  (e.g., coccinelle, sparse, smatch, clang-tidy)

Basic development tools (git, gcc, make, editors) should not be listed.

Example:

`Assisted-by: Claude:claude-3-opus coccinelle sparse`

