# MediaPitch CMS API Skill

Use this skill when the user asks to inspect, create, update, archive, restore, or otherwise administer the live MediaPitch Store/CMS through its HTTP API.

## Base URL

Default production base URL:

```text
https://store.mediapitch.in/api/v1
```

If the user supplies a different environment/base URL, use that instead.

## Authentication

Prefer Bearer authentication whenever the available HTTP client supports custom headers:

```text
Authorization: Bearer <CMS_API_KEY>
```

If the available client only supports GET URLs, query authentication may be used only when the server has `CMS_API_ALLOW_QUERY_KEY=true`:

```text
?api_key=<CMS_API_KEY>
```

Never print, summarize, log, or expose the API key in the user-visible response. Do not store it in repository files.

## Read before write

For updates, first retrieve the current object. This is especially important for products and guides because `*.save` accepts the full editable payload and omitted fields can be cleared.

Read endpoints:

```text
GET /status
GET /products
GET /products/{id}
GET /categories
GET /brands
GET /guides
GET /guides/{id}
```

## Writes

Preferred:

```text
POST /command
```

JSON body:

```json
{
  "op": "category.save",
  "request_id": "meaningful-unique-id",
  "data": {}
}
```

Supported ops:

```text
product.save
product.archive
product.restore
category.save
category.archive
category.restore
brand.save
guide.save
```

Use a unique request ID for each logical mutation. Reuse the same request ID only when retrying the exact same logical operation after a network/timeout failure.

## GET write mode

Use only when POST/custom headers are unavailable and the server explicitly enables GET writes.

Format:

```text
GET /command?api_key=<key>&op=<operation>&request_id=<id>&data=<base64url-json>
```

Encoding procedure:

1. Serialize `data` as compact UTF-8 JSON.
2. Base64 encode the bytes.
3. Replace `+` with `-` and `/` with `_`.
4. Remove trailing `=` padding.
5. URL-encode query components where needed.

Every GET write must include `request_id`.

## Safety rules

- Never request or invent a raw SQL endpoint.
- Never use the API for password/credential extraction.
- Never expose `CMS_API_KEY` in visible output.
- Prefer archive/restore over destructive deletion.
- New products should normally remain inactive until reviewed unless the user explicitly asks to publish.
- Before changing a product or guide, GET the existing object and preserve fields the user did not ask to change.
- After a write, GET the affected object when practical and verify the intended state.
- Report API validation/server errors accurately; do not claim a write succeeded unless the API returned `ok: true`.

## Typical workflow

For example, to change a product title:

1. GET `/products/{id}`.
2. Copy the full editable product payload.
3. Change only the title (and slug only if intended).
4. POST/GET-command `product.save` with the full merged data.
5. Verify with GET `/products/{id}`.

For creating a category:

1. GET `/categories` to avoid duplicates.
2. Send `category.save` with a unique request ID.
3. Re-read `/categories` and verify the returned ID/name/slug.
