# Query API — Live Test Results

Tests run against `http://plugins.local/subsite23` on 2026-04-10.
Test data: 50 posts + 50 pages generated via `scripts/populate-pt-content.php`, plus 1 post created via REST API.

## `/query` endpoint

### List all PT posts

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?per_page=2"
```

```json
[
  {
    "id": 111,
    "title": "Created via REST API",
    "date": "2026-04-10 08:21:11",
    "link": "http://plugins.local/subsite23/2026/04/10/created-via-rest-api/",
    "portable_text": [
      {
        "_type": "block",
        "_key": "rest1",
        "style": "normal",
        "children": [
          { "_type": "span", "_key": "s1", "text": "This post was created via the REST API with ", "marks": [] },
          { "_type": "span", "_key": "s2", "text": "Portable Text", "marks": ["strong"] },
          { "_type": "span", "_key": "s3", "text": " content.", "marks": [] }
        ],
        "markDefs": []
      }
    ],
    "matched_blocks": null
  },
  {
    "id": 60,
    "title": "WordPress Plugin Testing Guide",
    "date": "2026-04-10 08:18:39",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-plugin-testing-guide/",
    "portable_text": [ "..." ],
    "matched_blocks": null
  }
]
```

### Filter by style (`h2`)

```bash
curl -sD- "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?style=h2&per_page=3"
```

```
X-WP-Total: 52
X-WP-TotalPages: 18
```

```json
[
  { "id": 60, "title": "WordPress Plugin Testing Guide", "matched_blocks": [ { "_type": "block", "style": "h2", "..." } ] },
  { "id": 59, "title": "WordPress CI/CD Pipeline Setup", "matched_blocks": [ "..." ] },
  { "id": 58, "title": "Building a Custom Block Editor", "matched_blocks": [ "..." ] }
]
```

### Filter by annotation (`has=link`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?has=link&per_page=3"
```

```
X-WP-Total: 49
X-WP-TotalPages: 17
```

```json
[
  {
    "id": 60,
    "title": "WordPress Plugin Testing Guide",
    "date": "2026-04-10 08:18:39",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-plugin-testing-guide/",
    "portable_text": ["..."],
    "matched_blocks": [
      {
        "_type": "block",
        "style": "normal",
        "children": [
          { "_type": "span", "text": "For more details, check the ", "marks": [] },
          { "_type": "span", "text": "official documentation", "marks": ["k0012"] },
          { "_type": "span", "text": " which covers all the edge cases and advanced usage patterns.", "marks": [] }
        ],
        "markDefs": [{ "_type": "link", "_key": "k0012", "href": "https://developer.wordpress.org/" }]
      }
    ]
  },
  { "id": 59, "title": "WordPress CI/CD Pipeline Setup", "matched_blocks": ["..."] },
  { "id": 58, "title": "Building a Custom Block Editor", "matched_blocks": ["..."] }
]
```

### Filter by mark (`has=strong`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?has=strong&per_page=2"
```

```
X-WP-Total: 52
X-WP-TotalPages: 26
```

```json
[
  {
    "id": 111,
    "title": "Created via REST API",
    "date": "2026-04-10 08:21:11",
    "link": "http://plugins.local/subsite23/2026/04/10/created-via-rest-api/",
    "portable_text": ["..."],
    "matched_blocks": [
      {
        "_type": "block",
        "_key": "rest1",
        "style": "normal",
        "children": [
          { "_type": "span", "text": "This post was created via the REST API with ", "marks": [] },
          { "_type": "span", "text": "Portable Text", "marks": ["strong"] },
          { "_type": "span", "text": " content.", "marks": [] }
        ],
        "markDefs": []
      }
    ]
  },
  {
    "id": 60,
    "title": "WordPress Plugin Testing Guide",
    "portable_text": ["..."],
    "matched_blocks": ["..."]
  }
]
```

### Filter by block type (`break`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?block_type=break&per_page=3"
```

```json
[
  {
    "id": 60,
    "title": "WordPress Plugin Testing Guide",
    "date": "2026-04-10 08:18:39",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-plugin-testing-guide/",
    "portable_text": ["..."],
    "matched_blocks": [{ "_type": "break", "_key": "k0028" }]
  },
  {
    "id": 59,
    "title": "WordPress CI/CD Pipeline Setup",
    "date": "2026-04-10 08:18:39",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-ci-cd-pipeline-setup/",
    "portable_text": ["..."],
    "matched_blocks": [{ "_type": "break", "_key": "k0028" }]
  }
]
```

### Filter by style (`blockquote`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?style=blockquote&per_page=1"
```

```
X-WP-Total: 49
X-WP-TotalPages: 49
```

```json
[
  {
    "id": 60,
    "title": "WordPress Plugin Testing Guide",
    "date": "2026-04-10 08:18:39",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-plugin-testing-guide/",
    "portable_text": ["..."],
    "matched_blocks": [
      {
        "_type": "block",
        "style": "blockquote",
        "children": [{ "_type": "span", "text": "Premature optimization is the root of all evil.", "marks": ["em"] }],
        "markDefs": []
      }
    ]
  }
]
```

### Query pages (`post_type=page`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?post_type=page&per_page=2"
```

```
X-WP-Total: 51
X-WP-TotalPages: 26
```

```json
[
  {
    "id": 110,
    "title": "Press Kit and Resources",
    "date": "2026-04-10 08:18:39",
    "link": "http://plugins.local/subsite23/press-kit-and-resources/",
    "portable_text": ["..."],
    "matched_blocks": null
  },
  {
    "id": 109,
    "title": "Partner Program",
    "date": "2026-04-10 08:18:39",
    "link": "http://plugins.local/subsite23/partner-program/",
    "portable_text": ["..."],
    "matched_blocks": null
  }
]
```

## `/blocks` endpoint

### Extract code blocks

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?block_type=codeBlock&per_page=3"
```

```
X-WP-Total: 49
X-WP-TotalPages: 17
```

```json
[
  {
    "block": {
      "_type": "codeBlock",
      "_key": "k0022",
      "code": ".wp-portable-text-editor {\n  display: flex;\n  flex-directi...",
      "language": "css"
    },
    "post_id": 60,
    "title": "WordPress Plugin Testing Guide",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-plugin-testing-guide/"
  },
  {
    "block": {
      "_type": "codeBlock",
      "_key": "k0022",
      "code": "#!/bin/bash\n\n# Deploy WordPress plugin\nVERSION=$(jq -r .v...",
      "language": "bash"
    },
    "post_id": 59,
    "title": "WordPress CI/CD Pipeline Setup",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-ci-cd-pipeline-setup/"
  },
  {
    "block": {
      "_type": "codeBlock",
      "_key": "k0022",
      "code": "interface PortableTextBlock {\n  _type: string;\n  _key: str...",
      "language": "typescript"
    },
    "post_id": 58,
    "title": "Building a Custom Block Editor",
    "link": "http://plugins.local/subsite23/2026/04/10/building-a-custom-block-editor/"
  }
]
```

### Filter code blocks by language (`php`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?block_type=codeBlock&language=php&per_page=2"
```

```
X-WP-Total: 9
X-WP-TotalPages: 5
```

```json
[
  {
    "block": {
      "_type": "codeBlock",
      "_key": "k0022",
      "code": "<?php\n\nfunction get_custom_data( int $id ): array {\n    $...",
      "language": "php"
    },
    "post_id": 56,
    "title": "Headless WordPress with Next.js",
    "link": "http://plugins.local/subsite23/2026/04/10/headless-wordpress-with-next-js/"
  },
  {
    "block": {
      "_type": "codeBlock",
      "_key": "k0022",
      "code": "<?php\n\nfunction get_custom_data( int $id ): array {\n    $...",
      "language": "php"
    },
    "post_id": 51,
    "title": "WordPress Action Scheduler Guide",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-action-scheduler-guide/"
  }
]
```

### Extract h2 headings

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?block_type=block&style=h2&per_page=3"
```

```
X-WP-Total: 49
X-WP-TotalPages: 17
```

```json
[
  {
    "block": {
      "_type": "block",
      "_key": "k0001",
      "style": "h2",
      "children": [{ "_type": "span", "_key": "k0002", "text": "Documentation Writing Guide", "marks": [] }],
      "markDefs": []
    },
    "post_id": 60,
    "title": "WordPress Plugin Testing Guide",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-plugin-testing-guide/"
  },
  {
    "block": {
      "_type": "block",
      "_key": "k0001",
      "style": "h2",
      "children": [{ "_type": "span", "_key": "k0002", "text": "Code Review Best Practices", "marks": [] }],
      "markDefs": []
    },
    "post_id": 59,
    "title": "WordPress CI/CD Pipeline Setup",
    "link": "http://plugins.local/subsite23/2026/04/10/wordpress-ci-cd-pipeline-setup/"
  },
  {
    "block": {
      "_type": "block",
      "_key": "k0001",
      "style": "h2",
      "children": [{ "_type": "span", "_key": "k0002", "text": "Video Embedding Standards", "marks": [] }],
      "markDefs": []
    },
    "post_id": 58,
    "title": "Building a Custom Block Editor",
    "link": "http://plugins.local/subsite23/2026/04/10/building-a-custom-block-editor/"
  }
]
```

### Extract images from pages

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?block_type=image&post_type=page&per_page=3"
```

```
X-WP-Total: 100
X-WP-TotalPages: 5
```

```json
[
  {
    "block": {
      "_type": "image",
      "_key": "img110a",
      "src": "https://picsum.photos/seed/mountains110/800/450",
      "alt": "Mountains — illustration for Press Kit and Resources",
      "caption": "An overview of the key concepts discussed in this guide."
    },
    "post_id": 110,
    "title": "Press Kit and Resources",
    "link": "http://plugins.local/subsite23/press-kit-and-resources/"
  },
  {
    "block": {
      "_type": "image",
      "_key": "img110b",
      "src": "https://picsum.photos/seed/aerial110/720/480",
      "alt": "Aerial — related visual for Press Kit and Resources",
      "caption": "Illustration of the data flow pattern."
    },
    "post_id": 110,
    "title": "Press Kit and Resources",
    "link": "http://plugins.local/subsite23/press-kit-and-resources/"
  },
  {
    "block": {
      "_type": "image",
      "_key": "img109a",
      "src": "https://picsum.photos/seed/ocean109/960/540",
      "alt": "Ocean — illustration for Partner Program",
      "caption": "Overview of the system components."
    },
    "post_id": 109,
    "title": "Partner Program",
    "link": "http://plugins.local/subsite23/partner-program/"
  }
]
```

## Validation (error cases)

### Invalid `block_type` → 400

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?block_type=invalid"
```

```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): block_type",
  "data": {
    "status": 400,
    "params": {
      "block_type": "block_type is not one of block, image, codeBlock, embed, and break."
    }
  }
}
```

### `per_page` exceeds maximum → 400

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?per_page=999"
```

```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): per_page",
  "data": {
    "status": 400,
    "params": {
      "per_page": "per_page must be between 1 (inclusive) and 100 (inclusive)"
    }
  }
}
```

### Page beyond results → empty array

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?page=999&per_page=10"
```

```
X-WP-Total: 0
X-WP-TotalPages: 0

[]
```

### Missing required `block_type` on `/blocks` → 400

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?style=h2"
```

```json
{
  "code": "rest_missing_callback_param",
  "message": "Missing parameter(s): block_type",
  "data": {
    "status": 400,
    "params": ["block_type"]
  }
}
```
