# Query API

The plugin provides a GROQ-like query API under `/wp-json/wp-portable-text/v1/` that lets you search and extract Portable Text content across posts.

>Tests for the Query API are documented in [QUERY-TESTS.md](QUERY-TESTS.md) with live results from a test site.

## Endpoints

| Endpoint | Description |
| --- | --- |
| `GET /wp-json/wp-portable-text/v1/query` | Find posts matching block/content criteria |
| `GET /wp-json/wp-portable-text/v1/blocks` | Extract specific blocks across posts |

## `/query` — Find posts by content structure

Find posts that contain specific block types, styles, or marks.

**Parameters:**

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `post_type` | string | `post` | Post type to search |
| `block_type` | string | — | Filter by block `_type`: `block`, `image`, `codeBlock`, `embed`, `table`, `break` |
| `has` | string | — | Filter posts containing this mark or annotation (e.g., `link`, `strong`, `em`) |
| `style` | string | — | Filter posts containing blocks with this style (e.g., `h1`, `h2`, `blockquote`) |
| `per_page` | integer | `10` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

**Response headers:** `X-WP-Total`, `X-WP-TotalPages`, `X-WP-PT-Cache` (`HIT` or `MISS`)

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

## `/blocks` — Extract blocks across posts

Returns a flat list of blocks (with post context) matching the criteria. Useful for building indexes, galleries, or aggregations.

**Parameters:**

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `post_type` | string | `post` | Post type to search |
| `block_type` | string | **required** | Block type to extract: `block`, `image`, `codeBlock`, `embed`, `table`, `break` |
| `language` | string | — | Filter code blocks by language |
| `style` | string | — | Filter text blocks by style |
| `per_page` | integer | `20` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

**Response headers:** `X-WP-Total`, `X-WP-TotalPages`, `X-WP-PT-Cache` (`HIT` or `MISS`)

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

## JavaScript examples

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

## See also

- [REST API](REST.md) — Standard WordPress REST API fields and endpoints.

## `/block/{type}` — Block type archive pages

The plugin also provides frontend archive pages at `/block/{type}/` that display all blocks of a given type across posts, rendered as HTML with links to the source post. These pages use the active theme's header and footer.

| URL | Description |
| --- | --- |
| `/block/` | Index page listing all block types with counts |
| `/block/image/` | All images across all posts |
| `/block/codeBlock/` | All code blocks across all posts |
| `/block/block/` | All text blocks (paragraphs, headings, etc.) |
| `/block/embed/` | All embeds |
| `/block/table/` | All tables |
| `/block/break/` | All break blocks |
| `/block/image/page/2/` | Paginated results (20 per page) |

Each block is rendered as HTML and includes a "From: [Post Title]" link back to the source post.

The index page at `/block/` shows all available block types with the number of blocks of each type found across all posts.
