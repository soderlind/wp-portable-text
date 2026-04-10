# REST API

WP Portable Text adds a `portable_text` field to the WordPress REST API response for every public post type (`show_in_rest = true`). The field contains the raw Portable Text JSON array, or `null` if the content is not Portable Text.

## Endpoints

The field is available on all standard WordPress post endpoints:

| Endpoint | Description |
| --- | --- |
| `GET /wp-json/wp/v2/posts` | List posts |
| `GET /wp-json/wp/v2/posts/{id}` | Single post |
| `GET /wp-json/wp/v2/pages` | List pages |
| `GET /wp-json/wp/v2/pages/{id}` | Single page |
| `GET /wp-json/wp/v2/{custom-type}` | Any CPT with `show_in_rest` |

## Response fields

Each response includes both the rendered HTML and the raw Portable Text:

| Field | Type | Description |
| --- | --- | --- |
| `content.rendered` | `string` | Server-rendered HTML (via PHP renderer) |
| `content.raw` | `string` | Raw JSON string (requires `edit` context) |
| `portable_text` | `array\|null` | Parsed Portable Text blocks, or `null` |

## Examples

### Fetch a single post with Portable Text

```bash
curl -s "https://example.com/wp-json/wp/v2/posts/42?_fields=id,title,portable_text" | jq .
```

```json
{
  "id": 42,
  "title": {
    "rendered": "Hello World"
  },
  "portable_text": [
    {
      "_type": "block",
      "_key": "abc123",
      "style": "normal",
      "children": [
        {
          "_type": "span",
          "_key": "def456",
          "text": "Hello, world!",
          "marks": []
        }
      ],
      "markDefs": []
    }
  ]
}
```

### Fetch only the Portable Text field

Use `_fields` to minimize the response:

```bash
curl -s "https://example.com/wp-json/wp/v2/posts/42?_fields=portable_text"
```

### List posts with both HTML and Portable Text

```bash
curl -s "https://example.com/wp-json/wp/v2/posts?_fields=id,title,content,portable_text&per_page=5"
```

### Rich content example

A post with headings, bold text, links, an image, and a code block:

```json
{
  "portable_text": [
    {
      "_type": "block",
      "_key": "h1",
      "style": "h2",
      "children": [
        {
          "_type": "span",
          "_key": "s1",
          "text": "Getting Started",
          "marks": []
        }
      ],
      "markDefs": []
    },
    {
      "_type": "block",
      "_key": "p1",
      "style": "normal",
      "children": [
        {
          "_type": "span",
          "_key": "s2",
          "text": "Install the plugin and ",
          "marks": []
        },
        {
          "_type": "span",
          "_key": "s3",
          "text": "activate it",
          "marks": ["strong"]
        },
        {
          "_type": "span",
          "_key": "s4",
          "text": ". See the ",
          "marks": []
        },
        {
          "_type": "span",
          "_key": "s5",
          "text": "documentation",
          "marks": ["link1"]
        },
        {
          "_type": "span",
          "_key": "s6",
          "text": " for details.",
          "marks": []
        }
      ],
      "markDefs": [
        {
          "_type": "link",
          "_key": "link1",
          "href": "https://example.com/docs"
        }
      ]
    },
    {
      "_type": "image",
      "_key": "img1",
      "src": "https://example.com/wp-content/uploads/2026/04/screenshot.png",
      "alt": "Screenshot of the editor",
      "caption": "The Portable Text editor in action",
      "attachmentId": 99
    },
    {
      "_type": "codeBlock",
      "_key": "cb1",
      "code": "const blocks = await fetch('/wp-json/wp/v2/posts/42')\n  .then(r => r.json())\n  .then(p => p.portable_text);",
      "language": "javascript"
    }
  ]
}
```

### List items

Bullet and numbered lists use the `listItem` and `level` properties:

```json
{
  "portable_text": [
    {
      "_type": "block",
      "_key": "li1",
      "style": "normal",
      "listItem": "bullet",
      "level": 1,
      "children": [
        { "_type": "span", "_key": "s1", "text": "First item", "marks": [] }
      ],
      "markDefs": []
    },
    {
      "_type": "block",
      "_key": "li2",
      "style": "normal",
      "listItem": "bullet",
      "level": 1,
      "children": [
        { "_type": "span", "_key": "s2", "text": "Second item", "marks": [] }
      ],
      "markDefs": []
    }
  ]
}
```

## Consuming with JavaScript

### Fetch and render with @portabletext/react

```jsx
import { PortableText } from '@portabletext/react';

const response = await fetch('/wp-json/wp/v2/posts/42?_fields=portable_text');
const { portable_text } = await response.json();

function Post() {
  return <PortableText value={portable_text} />;
}
```

### Fetch and render with @portabletext/to-html

```js
import { toHTML } from '@portabletext/to-html';

const response = await fetch('/wp-json/wp/v2/posts/42?_fields=portable_text');
const { portable_text } = await response.json();

const html = toHTML(portable_text);
```

### Using with any framework

Portable Text is framework-agnostic. Official serializers exist for:

- **React** — [@portabletext/react](https://github.com/portabletext/react-portabletext)
- **HTML** — [@portabletext/to-html](https://github.com/portabletext/to-html)
- **Svelte** — [@portabletext/svelte](https://github.com/portabletext/svelte-portabletext)
- **Vue** — [@portabletext/vue](https://github.com/portabletext/vue-portabletext)
- **Astro** — [astro-portabletext](https://github.com/theisel/astro-portabletext)

## Schema reference

### Block types

| `_type` | Description | Additional fields |
| --- | --- | --- |
| `block` | Text block (paragraph, heading, etc.) | `style`, `children`, `markDefs`, `listItem`, `level` |
| `image` | Image | `src`, `alt`, `caption`, `attachmentId` |
| `codeBlock` | Code block | `code`, `language` |
| `embed` | oEmbed | `url` |
| `break` | Horizontal rule | — |

### Styles

`normal`, `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `blockquote`

### Decorators (marks)

`strong`, `em`, `underline`, `strike-through`, `code`, `subscript`, `superscript`

### Annotations (data-carrying marks)

| Name | Fields |
| --- | --- |
| `link` | `href` (string) |

### List types

`bullet`, `number`

## Authentication

The `portable_text` field is read-only and publicly available (same visibility as `content.rendered`). No authentication is needed for published posts.

For draft/private posts, use standard WordPress authentication (application passwords, cookie auth, or JWT).

---

## Query API

The plugin provides a GROQ-like query API under `/wp-json/wp-portable-text/v1/` that lets you search and extract Portable Text content across posts.

### Endpoints

| Endpoint | Description |
| --- | --- |
| `GET /wp-json/wp-portable-text/v1/query` | Find posts matching block/content criteria |
| `GET /wp-json/wp-portable-text/v1/blocks` | Extract specific blocks across posts |

### `/query` — Find posts by content structure

Find posts that contain specific block types, styles, or marks.

**Parameters:**

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `post_type` | string | `post` | Post type to search |
| `block_type` | string | — | Filter by block `_type`: `block`, `image`, `codeBlock`, `embed`, `break` |
| `has` | string | — | Filter posts containing this mark or annotation (e.g., `link`, `strong`, `em`) |
| `style` | string | — | Filter posts containing blocks with this style (e.g., `h1`, `h2`, `blockquote`) |
| `per_page` | integer | `10` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

**Response headers:** `X-WP-Total`, `X-WP-TotalPages`

**Examples:**

Find all posts containing images:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/query?block_type=image" | jq .
```

```json
[
  {
    "id": 42,
    "title": "My Photo Post",
    "date": "2026-04-10 12:00:00",
    "link": "https://example.com/my-photo-post/",
    "portable_text": [ ... ],
    "matched_blocks": [
      {
        "_type": "image",
        "_key": "img1",
        "src": "https://example.com/wp-content/uploads/photo.jpg",
        "alt": "A photo"
      }
    ]
  }
]
```

Find posts containing links:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/query?has=link"
```

Find posts with h2 headings:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/query?style=h2"
```

Find posts using bold text:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/query?has=strong"
```

Search pages instead of posts:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/query?post_type=page&block_type=codeBlock"
```

### `/blocks` — Extract blocks across posts

Returns a flat list of blocks (with post context) matching the criteria. Useful for building indexes, galleries, or aggregations.

**Parameters:**

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `post_type` | string | `post` | Post type to search |
| `block_type` | string | **required** | Block type to extract: `block`, `image`, `codeBlock`, `embed`, `break` |
| `language` | string | — | Filter code blocks by language |
| `style` | string | — | Filter text blocks by style |
| `per_page` | integer | `20` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

**Response headers:** `X-WP-Total`, `X-WP-TotalPages`

**Examples:**

Get all images across all posts (e.g., for a site-wide gallery):

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/blocks?block_type=image" | jq .
```

```json
[
  {
    "block": {
      "_type": "image",
      "_key": "img1",
      "src": "https://example.com/wp-content/uploads/sunset.jpg",
      "alt": "Sunset over the lake",
      "caption": "Photo by Jane Doe"
    },
    "post_id": 42,
    "title": "Weekend Trip",
    "link": "https://example.com/weekend-trip/"
  },
  {
    "block": {
      "_type": "image",
      "_key": "img2",
      "src": "https://example.com/wp-content/uploads/mountain.jpg",
      "alt": "Mountain view"
    },
    "post_id": 55,
    "title": "Hiking Guide",
    "link": "https://example.com/hiking-guide/"
  }
]
```

Get all PHP code blocks:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/blocks?block_type=codeBlock&language=php"
```

Get all JavaScript code blocks:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/blocks?block_type=codeBlock&language=javascript"
```

Get all h2 headings across posts:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/blocks?block_type=block&style=h2"
```

Paginate through results:

```bash
curl -s "https://example.com/wp-json/wp-portable-text/v1/blocks?block_type=image&per_page=5&page=2"
```

### JavaScript examples

Build a site-wide image gallery:

```js
const response = await fetch('/wp-json/wp-portable-text/v1/blocks?block_type=image&per_page=50');
const images = await response.json();
const total = response.headers.get('X-WP-Total');

images.forEach(({ block, title, link }) => {
  console.log(`${block.alt} — from "${title}" (${link})`);
});
```

Find posts with code snippets in a specific language:

```js
const response = await fetch('/wp-json/wp-portable-text/v1/query?block_type=codeBlock');
const posts = await response.json();

posts.forEach(post => {
  const codeBlocks = post.matched_blocks;
  console.log(`${post.title}: ${codeBlocks.length} code block(s)`);
});
```
