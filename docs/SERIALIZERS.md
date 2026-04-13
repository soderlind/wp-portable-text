# Serializers

The plugin uses a **Strategy pattern** to convert Portable Text blocks into different output formats. A shared walker in `Renderer` traverses the PT tree and delegates leaf-level rendering to a `Serializer` implementation.

## Architecture

```
Renderer (walker)
  └─ blocks_to_format( $blocks, Serializer )
       ├─ walk_block()       → dispatches by _type
       ├─ walk_text_block()  → renders children, wraps in style
       ├─ walk_children()    → applies marks via serializer
       └─ group_list_items() → batches consecutive list items
```

Two built-in serializers are provided:

| Class | Output | Used by |
| --- | --- | --- |
| `Html_Serializer` | HTML | `the_content` filter, REST API `content.rendered`, revision diffs |
| `Markdown_Serializer` | Markdown | `?format=markdown`, `Accept: text/markdown`, `<link rel="alternate">` |

Both implement the `Serializer` interface defined in `includes/Serializers/Serializer.php`.

## Interface methods

Every serializer must implement these methods:

| Method | Purpose |
| --- | --- |
| `escape_text( $text )` | Escape raw span text for the target format |
| `wrap_decorator( $text, $decorator )` | Wrap text with a decorator (`strong`, `em`, `code`, …) |
| `wrap_annotation( $text, $def )` | Wrap text with a data-carrying mark (`link`, …) |
| `render_inline_object( $child )` | Render an inline object (non-span child) |
| `render_text_block( $content, $style )` | Wrap rendered children in a block (`normal`, `h1`–`h6`, `blockquote`) |
| `render_break()` | Render a horizontal rule |
| `render_image( $block )` | Render an image block |
| `render_code_block( $block )` | Render a fenced code block |
| `render_embed( $block )` | Render an embed/oEmbed block |
| `render_table( $block )` | Render a table block |
| `render_list( $items, $list_type )` | Render a list from pre-rendered item strings |
| `render_unknown_block( $block )` | Handle unknown/custom block types |
| `join_blocks( $parts )` | Join rendered block strings into final output |

## Built-in serializers

### HTML (`Html_Serializer`)

Produces WordPress-safe HTML. Key behaviors:

- **Text escaping**: Uses `esc_html()`.
- **Links**: Validates URIs (rejects `javascript:`, `data:`, `vbscript:`), applies `esc_url()`, adds `rel="noopener noreferrer"` for external links.
- **Images**: Wrapped in `<figure class="wp-portable-text-image">` with optional `<figcaption>`.
- **Tables**: Uses `<table class="wp-portable-text-table">` with optional `<thead>` when `hasHeaderRow` is set.
- **Embeds**: Delegates to `$wp_embed->shortcode()` for oEmbed resolution, falls back to a plain link.
- **Unknown blocks**: Defers to the `wp_portable_text_render_block` filter (returns empty string by default).

### Markdown (`Markdown_Serializer`)

Produces CommonMark-compatible Markdown. Key behaviors:

- **Text escaping**: No escaping (raw text pass-through).
- **Decorators**: `**bold**`, `*italic*`, `` `code` ``, `~~strikethrough~~`. Underline/sub/superscript use inline HTML (`<u>`, `<sub>`, `<sup>`).
- **Links**: `[text](href)`.
- **Images**: `![alt](src)` with optional `*caption*` below.
- **Code blocks**: Triple-backtick fences with language hint.
- **Tables**: Pipe-delimited Markdown tables with `---` separator.
- **Block joining**: Double newline between blocks (vs. concatenation in HTML).

## Client-side serializers

The editor bundles TypeScript equivalents in `src/editor/serializers.ts` for live preview:

| Function | Output | Usage |
| --- | --- | --- |
| `ptToHtml( blocks )` | HTML string | Preview panel rendering |
| `ptToMarkdown( blocks )` | Markdown string | Markdown preview/export |

These mirror the PHP serializer logic but run in the browser. They are not pluggable — customization should happen server-side via PHP filters.

## Extending: custom serializer

Create a class implementing the `Serializer` interface and pass it to `blocks_to_format()`:

```php
use WPPortableText\Serializers\Serializer;

class Rss_Serializer implements Serializer {
    public function escape_text( string $text ): string {
        return htmlspecialchars( $text, ENT_XML1, 'UTF-8' );
    }

    public function wrap_decorator( string $text, string $decorator ): string {
        // Strip all formatting for RSS plain content.
        return $text;
    }

    // … implement remaining interface methods …
}
```

Then call it via the renderer:

```php
$renderer = new \WPPortableText\Renderer();
$blocks   = json_decode( $post->post_content, true );
// Use reflection or a public wrapper to call blocks_to_format().
```

> **Note:** `blocks_to_format()` is currently `private`. To use a custom serializer without modifying the plugin, use the extension filters below instead.

## Extension filters

The HTML serializer provides filters for injecting custom rendering without writing a full serializer:

| Filter | Arguments | Description |
| --- | --- | --- |
| `wp_portable_text_render_block` | `$html`, `$block` | Render an unknown block `_type` |
| `wp_portable_text_render_annotation` | `$html`, `$def` | Render an unknown annotation type |
| `wp_portable_text_render_inline` | `$html`, `$child` | Render an inline object |

**Example — render a custom `callout` block type:**

```php
add_filter( 'wp_portable_text_render_block', function ( string $html, array $block ): string {
    if ( 'callout' !== ( $block['_type'] ?? '' ) ) {
        return $html;
    }

    $text = esc_html( $block['text'] ?? '' );
    $type = esc_attr( $block['calloutType'] ?? 'info' );

    return "<div class=\"callout callout-{$type}\"><p>{$text}</p></div>\n";
}, 10, 2 );
```

## See also

- [REST API](REST.md) — How the `portable_text` field is exposed.
- [Query API](QUERY.md) — Searching content by block type, style, and marks.
