# MediaPitch Store

Affiliate product-discovery and editorial buying-guide platform for MediaPitch.

The application uses one internal catalog/content model regardless of whether products are entered manually, imported from Amazon Creators API, or enriched with editorial overrides.

## MediaPitch Store v1

The code-side v1 MVP is complete on `main` and includes:

- PHP 8.2 + MariaDB CMS/storefront
- roles, authentication, password recovery and audit logging
- products, nested categories, brands and specifications
- media library with optimization/thumbnails
- buying guides, blog posts, reviews and comparisons
- unified search, SEO metadata, structured data, sitemap and redirects
- affiliate click tracking and analytics
- homepage merchandising
- optional Amazon Creators API integration with encrypted multi-marketplace profiles
- stale Amazon product refresh/price freshness handling
- authenticated CMS Admin API v1 for trusted automation/agent administration
- production schema repair/verification and smoke-test tooling

## Production commands

After deploying the latest `main`:

```bash
composer deploy-db
composer smoke-test
```

For the complete production sign-off procedure, see:

- `docs/RELEASE-V1.md`
- `docs/DEPLOYMENT.md`
- `docs/CMS-ADMIN-API.md`
- `docs/AMAZON-COMPLIANCE.md`
- `TASKS.md`

## CMS Admin API

The authenticated API lives under `/api/v1`. It supports reads and whitelisted mutations across catalog/editorial resources, media metadata, specifications, site settings, merchandising and configured Amazon marketplace operations. There is deliberately no raw SQL or arbitrary command endpoint.

Preferred authentication is a Bearer token or `X-API-Key`. Query-key/GET-write mode exists only for constrained clients and should be disabled when a header-capable POST client is available.

See `docs/CMS-ADMIN-API.md` for the complete contract.

## Local setup

1. Copy `.env.example` to `.env`.
2. Add MariaDB connection values and a long random `APP_KEY`.
3. Run:

```bash
composer deploy-db
composer smoke-test
```

4. Point the web server document root to `public/` where possible. Root-document-root compatibility is also included for shared hosting.
5. Ensure PHP has PDO MySQL enabled. GD is optional; image processing falls back gracefully without it.

The project intentionally avoids unnecessary third-party PHP dependencies so it remains practical on standard PHP/shared hosting.
