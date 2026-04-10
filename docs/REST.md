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

The `portable_text` field is **read-write**. When reading, it returns the parsed PT blocks. When writing (POST/PUT), it validates the structure and stores the JSON in `post_content`, bypassing kses. Authentication is required for writes.

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

Reading the `portable_text` field is publicly available for published posts (same as `content.rendered`). **Writing requires authentication** — use application passwords, cookie auth, or JWT.

For draft/private posts, reading also requires authentication.

## Creating and updating posts

Send a `portable_text` array in the request body to create or update posts with Portable Text content. The plugin validates the PT structure, writes JSON directly to `post_content` (bypassing kses), and populates `post_content_filtered` with plaintext for search.

### Create a post

```bash
curl -X POST "https://example.com/wp-json/wp/v2/posts" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First PT Post",
    "status": "publish",
    "portable_text": [
      {
        "_type": "block",
        "_key": "intro",
        "style": "h2",
        "children": [
          {"_type": "span", "_key": "s1", "text": "Welcome", "marks": []}
        ],
        "markDefs": []
      },
      {
        "_type": "block",
        "_key": "p1",
        "style": "normal",
        "children": [
          {"_type": "span", "_key": "s2", "text": "This post was created via the ", "marks": []},
          {"_type": "span", "_key": "s3", "text": "REST API", "marks": ["strong"]},
          {"_type": "span", "_key": "s4", "text": " using Portable Text.", "marks": []}
        ],
        "markDefs": []
      }
    ]
  }'
```

### Update an existing post

```bash
curl -X POST "https://example.com/wp-json/wp/v2/posts/42" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "portable_text": [
      {
        "_type": "block",
        "_key": "p1",
        "style": "normal",
        "children": [
          {"_type": "span", "_key": "s1", "text": "Updated content.", "marks": []}
        ],
        "markDefs": []
      }
    ]
  }'
```

### Create a page

```bash
curl -X POST "https://example.com/wp-json/wp/v2/pages" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "About Us",
    "status": "publish",
    "portable_text": [
      {
        "_type": "block",
        "_key": "p1",
        "style": "normal",
        "children": [
          {"_type": "span", "_key": "s1", "text": "We build great things.", "marks": []}
        ],
        "markDefs": []
      }
    ]
  }'
```

### Create with rich content (links, lists, code blocks)

```bash
curl -X POST "https://example.com/wp-json/wp/v2/posts" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Rich Content Example",
    "status": "publish",
    "portable_text": [
      {
        "_type": "block",
        "_key": "p1",
        "style": "normal",
        "children": [
          {"_type": "span", "_key": "s1", "text": "Visit the ", "marks": []},
          {"_type": "span", "_key": "s2", "text": "WordPress site", "marks": ["link1"]},
          {"_type": "span", "_key": "s3", "text": " for more info.", "marks": []}
        ],
        "markDefs": [
          {"_type": "link", "_key": "link1", "href": "https://wordpress.org"}
        ]
      },
      {
        "_type": "block",
        "_key": "li1",
        "style": "normal",
        "listItem": "bullet",
        "level": 1,
        "children": [
          {"_type": "span", "_key": "s4", "text": "First item", "marks": []}
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
          {"_type": "span", "_key": "s5", "text": "Second item", "marks": []}
        ],
        "markDefs": []
      },
      {
        "_type": "codeBlock",
        "_key": "cb1",
        "code": "console.log(\"Hello from PT!\");",
        "language": "javascript"
      }
    ]
  }'
```

### JavaScript example

```js
const response = await fetch('/wp-json/wp/v2/posts', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Basic ' + btoa('username:xxxx xxxx xxxx xxxx xxxx xxxx'),
  },
  body: JSON.stringify({
    title: 'Created from JS',
    status: 'publish',
    portable_text: [
      {
        _type: 'block',
        _key: 'p1',
        style: 'normal',
        children: [
          { _type: 'span', _key: 's1', text: 'Hello from JavaScript!', marks: [] },
        ],
        markDefs: [],
      },
    ],
  }),
});

const post = await response.json();
console.log(`Created post #${post.id}`);
```

### Validation

The endpoint validates that `portable_text` is a sequential array where each block has a `_type` property. Invalid payloads return a `400` error:

```json
{
  "code": "invalid_portable_text",
  "message": "Each block must have a _type property.",
  "data": { "status": 400 }
}
```

## See also

- [Query API](QUERY.md) — GROQ-like endpoints for searching and extracting Portable Text content across posts.
