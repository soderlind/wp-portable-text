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
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?has=link&per_page=50"
```

```
Results with links: 49
  Post 60: WordPress Plugin Testing Guide — 1 matched blocks
  Post 59: WordPress CI/CD Pipeline Setup — 1 matched blocks
  Post 58: Building a Custom Block Editor — 1 matched blocks
  Post 57: WordPress Performance Checklist — 1 matched blocks
  ...
  Post 12: Modern CSS Grid Layout Techniques — 1 matched blocks
```

### Filter by mark (`has=strong`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?has=strong&per_page=3"
```

```
X-WP-Total: 52
X-WP-TotalPages: 18

Results: 3
  Post 111: Created via REST API — 1 matched blocks
  Post 60: WordPress Plugin Testing Guide — 1 matched blocks
  Post 59: WordPress CI/CD Pipeline Setup — 1 matched blocks
```

### Filter by block type (`break`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?block_type=break&per_page=3"
```

```
Posts with break blocks: 2
  Post 60: WordPress Plugin Testing Guide — 1 matched
  Post 59: WordPress CI/CD Pipeline Setup — 1 matched
```

### Filter by style (`blockquote`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?style=blockquote&per_page=50"
```

```
Posts with blockquote: 49
```

### Query pages (`post_type=page`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/query?post_type=page&per_page=3"
```

```
X-WP-Total: 51
X-WP-TotalPages: 17

Page results: 3
  Page 110: Press Kit and Resources — 13 blocks
  Page 109: Partner Program — 13 blocks
  Page 108: Compliance Information — 13 blocks
```

## `/blocks` endpoint

### Extract code blocks

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?block_type=codeBlock&per_page=5"
```

```
X-WP-Total: 49
X-WP-TotalPages: 10

Code blocks returned: 5
  [css]        from Post 60: "WordPress Plugin Testing Guide"
  [bash]       from Post 59: "WordPress CI/CD Pipeline Setup"
  [typescript] from Post 58: "Building a Custom Block Editor"
  [javascript] from Post 57: "WordPress Performance Checklist"
  [php]        from Post 56: "Headless WordPress with Next.js"
```

### Filter code blocks by language (`php`)

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?block_type=codeBlock&language=php&per_page=5"
```

```
X-WP-Total: 9
X-WP-TotalPages: 2

PHP code blocks: 5
  Post 56: "Headless WordPress with Next.js"
  Post 51: "WordPress Action Scheduler Guide"
  Post 46: "Email Templating in WordPress"
  Post 41: "WordPress Rewrite API Guide"
  Post 36: "WordPress Object Caching Deep Dive"
```

### Extract h2 headings

```bash
curl -s "http://plugins.local/subsite23/wp-json/wp-portable-text/v1/blocks?block_type=block&style=h2&per_page=3"
```

```
X-WP-Total: 49
X-WP-TotalPages: 17

h2 blocks: 3
  Post 60: "Documentation Writing Guide"
  Post 59: "Code Review Best Practices"
  Post 58: "Video Embedding Standards"
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
