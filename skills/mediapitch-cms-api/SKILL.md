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

## Read endpoints

```text
GET /status
GET /products
GET /products/{id}
GET /categories
GET /brands
GET /guides
GET /guides/{id}
GET /blog
GET /blog/{id}
GET /reviews
GET /reviews/{id}
GET /comparisons
GET /comparisons/{id}
GET /redirects
GET /redirects/{id}
GET /media
GET /media/{id}
GET /specifications
GET /specifications/{id}
GET /site-settings
GET /merchandising
GET /amazon/profiles
GET /amazon/search?q=...
```

Existing-record save operations are patch-safe. Still read before write when practical so the requested mutation can be verified against current state and accidental semantic changes can be avoided.

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
blog.save
review.save
comparison.save
redirect.save
media.alt.save
media.assign_category
specification.save
specification.archive
specification.restore
site-settings.save
merchandising.save
amazon.activate
amazon.test
amazon.refresh
amazon.import
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

## Resource-specific rules

- New products normally remain inactive until reviewed unless the user explicitly asks to activate them.
- Media binary upload/delete stays in the normal admin Media Library. The API can read media, change alt text, and assign an existing image to a category.
- Amazon credential management stays in the administrator UI. API calls may inspect safe profile status, activate a configured marketplace, test credentials, search Amazon, refresh stale products, and import/refetch an ASIN.
- Amazon API responses must never expose credential IDs, credential secrets, or access tokens.
- Prefer archive/restore over destructive deletion.

## Safety rules

- Never request or invent a raw SQL endpoint.
- Never use the API for password/credential extraction.
- Never expose `CMS_API_KEY` in visible output.
- Before a material update, GET the current object when practical.
- After a write, GET the affected object when practical and verify the intended state.
- Report API validation/server errors accurately; do not claim a write succeeded unless the API returned `ok: true`.

## Typical workflow

To change a product title:

1. GET `/products/{id}`.
2. Send `product.save` with `id` and the title field to change. Omitted editable fields are preserved.
3. Verify with GET `/products/{id}`.

To create a category:

1. GET `/categories` to avoid duplicates.
2. Send `category.save` with a unique request ID.
3. Re-read `/categories` and verify the returned ID/name/slug.

To change homepage merchandising:

1. GET `/merchandising`.
2. Send `merchandising.save` with only the intended `featured_ids`, `deal_ids`, or `deals_title` changes.
3. GET `/merchandising` and verify ordering/state.

To import an Amazon ASIN:

1. GET `/amazon/profiles` and confirm the intended marketplace is enabled/configured.
2. Optionally GET `/amazon/search?q=...` to confirm the product.
3. Send `amazon.import` with ASIN, optional category ID, and marketplace.
4. GET the returned product ID and review before activation.
