# MediaPitch CMS Admin API

The CMS exposes an authenticated API at `/api/v1` for trusted automation and AI-assisted administration.

## Authentication

Preferred for normal clients:

```http
Authorization: Bearer <CMS_API_KEY>
```

or:

```http
X-API-Key: <CMS_API_KEY>
```

For GET-only clients that cannot set headers, set `CMS_API_ALLOW_QUERY_KEY=true` and pass:

```text
?api_key=<CMS_API_KEY>
```

Query-string API keys can appear in server/access logs. Use a dedicated revocable key and rotate it when needed.

Recommended key generation:

```bash
openssl rand -hex 32
```

## Read endpoints

```text
GET /api/v1/status
GET /api/v1/products
GET /api/v1/products/{id}
GET /api/v1/categories
GET /api/v1/brands
GET /api/v1/guides
GET /api/v1/guides/{id}
GET /api/v1/blog
GET /api/v1/blog/{id}
GET /api/v1/reviews
GET /api/v1/reviews/{id}
GET /api/v1/comparisons
GET /api/v1/comparisons/{id}
GET /api/v1/redirects
GET /api/v1/redirects/{id}
```

All responses are JSON and use `Cache-Control: no-store`.

## Write commands

Preferred method:

```http
POST /api/v1/command
Content-Type: application/json
Authorization: Bearer <CMS_API_KEY>

{
  "op": "category.save",
  "request_id": "category-phones-20260818-001",
  "data": {
    "name": "Phones",
    "slug": "phones",
    "active": true
  }
}
```

Supported operations in v1:

- `product.save`
- `product.archive`
- `product.restore`
- `category.save`
- `category.archive`
- `category.restore`
- `brand.save`
- `guide.save`
- `blog.save`
- `review.save`
- `comparison.save`
- `redirect.save`

`id` in `data` updates an existing record. Without `id`, the operation creates a record.

Existing-record updates are patch-safe: the API loads the stored record first and preserves omitted editable fields. Product updates also preserve current specification values unless replacements are supplied. Guide updates preserve existing ranked products unless new product arrays are supplied.

## GET command mode

GET writes are disabled by default. Enable deliberately:

```env
CMS_API_ALLOW_QUERY_KEY=true
CMS_API_ALLOW_GET_WRITES=true
```

GET write format:

```text
/api/v1/command?api_key=...&op=category.save&request_id=category-phones-20260818-001&data=<BASE64URL_JSON>
```

`data` is UTF-8 JSON encoded using URL-safe Base64 without requiring padding. Example JSON before encoding:

```json
{"name":"Phones","slug":"phones","active":true}
```

Every GET write requires a unique `request_id`. Completed request IDs are stored through the settings table and replay the prior result rather than performing the mutation again.

## Safety model

- There is no raw SQL endpoint.
- Only whitelisted CMS operations are callable.
- API responses never intentionally expose password hashes, credential secrets, or access tokens.
- Writes are added to the CMS audit log when audit storage is available.
- Newly created products default to inactive unless `active` is explicitly supplied.
- Existing record updates preserve omitted fields rather than treating omissions as deletion requests.
- GET writes and query-string authentication are opt-in.
- Keep `CMS_API_KEY` out of Git, screenshots and public URLs.

## Suggested production configuration for ChatGPT-assisted administration

```env
CMS_API_KEY=<64-hex-random-value>
CMS_API_ALLOW_QUERY_KEY=true
CMS_API_ALLOW_GET_WRITES=true
CMS_API_AUTHOR_ID=0
```

`CMS_API_AUTHOR_ID=0` automatically selects the first active administrator/editor for authored content. Set a specific user ID when attribution should always go to one CMS user.

Once a client capable of Bearer-authenticated POST is available, query-string authentication and GET writes can be disabled again.
