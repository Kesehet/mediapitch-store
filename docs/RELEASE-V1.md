# MediaPitch Store v1 Production Sign-off

Use this checklist after deploying the latest `main` branch.

## 1. Deploy and database

Run from the deployed project directory:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
composer deploy-db
composer smoke-test
```

Expected outcome:

- database migrations/repairs complete without error
- schema verification passes
- bootstrap-admin step does not expose secrets
- smoke-test summary reports zero failures

If `BOOTSTRAP_ADMIN_PASSWORD` was only needed for account recovery, blank it after confirming normal login works.

## 2. Basic HTTP checks

Verify:

```text
GET /health                         -> status ok, database ok
GET /                              -> storefront loads
GET /admin/login                   -> login form loads
GET /sitemap.xml                   -> XML sitemap loads
GET /robots.txt                    -> robots file loads
```

## 3. Admin CRUD smoke test

Use disposable/inactive test records where practical.

### Categories
- create inactive test category
- edit name/SEO fields
- archive/restore

### Brands
- create test brand
- edit website/logo fields
- archive/restore

### Products
- create inactive manual product
- edit title/slug/specifications/gallery
- confirm slug change creates redirect
- duplicate product and confirm duplicate is inactive
- archive/restore

### Media
- upload valid image
- confirm thumbnail/optimization behavior
- update category image assignment
- confirm in-use media cannot be deleted
- delete unused test media

### Blog
- create draft article
- add tags and featured image
- preview/save
- publish only if appropriate

### Buying guides
- create draft guide
- add ranked products
- save/reorder and confirm ranking persists

### Reviews
- create draft review linked to product
- verify score validation

### Comparisons
- create comparison with at least two products
- verify public comparison table/spec matrix

### Audit / analytics / settings
- confirm recent test mutations appear in Audit
- open analytics without SQL/schema errors
- save site settings and reload
- save merchandising and reload homepage

Delete/archive disposable records when finished.

## 4. CMS Admin API v1

Use Bearer authentication when possible. Never paste production keys into public logs or documentation.

Read checks:

```text
GET /api/v1/status
GET /api/v1/products
GET /api/v1/categories
GET /api/v1/brands
GET /api/v1/guides
GET /api/v1/blog
GET /api/v1/reviews
GET /api/v1/comparisons
GET /api/v1/redirects
GET /api/v1/media
GET /api/v1/specifications
GET /api/v1/site-settings
GET /api/v1/merchandising
GET /api/v1/amazon/profiles
```

Confirm `/api/v1/amazon/profiles` never returns credential IDs or credential secrets.

Write checks should use unique `request_id` values and disposable/inactive records. At minimum verify:

```text
category.save
product.save
blog.save
redirect.save
media.alt.save
specification.save
site-settings.save
merchandising.save
```

After each write, re-read the resource and verify the intended state. Retry the same request ID once and confirm the prior result is replayed rather than duplicated.

## 5. Amazon integration (only if enabled)

For each intended marketplace:

- profile is enabled
- partner tag and encrypted credentials are configured
- connection test succeeds
- search returns items
- import creates/refreshes an inactive product draft
- stale refresh succeeds
- legacy blank-marketplace products are reviewed before more than one marketplace is enabled

Cron example:

```cron
15 * * * * cd /path/to/mediapitch-store && /usr/bin/php database/refresh-amazon.php 100 >> storage-amazon-cron.log 2>&1
```

Use the host's actual PHP binary/project path and place logs somewhere not web-accessible.

## 6. Public visual QA

Check desktop and mobile:

- homepage
- category page
- brand page
- product page
- buying guide
- blog article/index
- review
- comparison
- search

Confirm:

- MediaPitch branding/logo looks correct
- navigation and search work
- no horizontal overflow
- focus/keyboard navigation works
- product images are not stretched
- disclosures render cleanly
- Amazon prices/details do not show stale pricing beyond the configured freshness window
- canonical/OG metadata uses the production host

## 7. Security cleanup

After sign-off:

- set `APP_DEBUG=false` in production
- blank `BOOTSTRAP_ADMIN_PASSWORD` if no longer needed
- rotate the temporary CMS API key used during setup/testing
- prefer Bearer/X-API-Key clients and set `CMS_API_ALLOW_QUERY_KEY=false` when query-key access is no longer needed
- set `CMS_API_ALLOW_GET_WRITES=false` when a POST-capable agent/client is available
- keep `.env`, logs and database backups outside public access

## v1 completion condition

MediaPitch Store v1 is signed off when deployment/schema/smoke tests pass, the admin/API CRUD checks complete without errors, required Amazon marketplace checks pass if enabled, and final public/admin visual QA is accepted.
