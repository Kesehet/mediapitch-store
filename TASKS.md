# MediaPitch Store | Project Task Tracker

This file is the source of truth for work landing on `main`.

## Status
- [x] Done
- [~] In progress
- [ ] Remaining
- [!] Needs production/external verification

## Current snapshot
- Production branch: `main`
- Legacy `agent/*` branches: fast-forwarded to current `main`
- Production URL: `https://store.mediapitch.in/`
- Production `/health`: DB connection confirmed OK
- PHP syntax GitHub Action: confirmed passing on recent commits
- Deployment: Composer hook is resilient; MariaDB migrations are retry-safe and no longer wrap DDL in PDO transactions
- DB deploy: base schema -> migrations -> non-destructive seed defaults
- Bootstrap admin: created only when user count is zero
- Public page titles use `|` separators

---

# 1. Foundation, deployment & operations
- [x] PHP 8.2 application structure, autoloading and PDO/MariaDB layer
- [x] `.env` configuration and production-host URL fallback
- [x] Root/public front-controller and Apache rewrite compatibility
- [x] `/health`, 404, 500 and DB-fallback behavior
- [x] GitHub Actions PHP syntax checks
- [x] Schema bootstrap, migration runner and migration tracking
- [x] Retry-safe MariaDB DDL migrations
- [x] Non-destructive seed defaults
- [x] Empty-user-table bootstrap administrator
- [x] Resilient Composer DB deployment hook
- [ ] `.editorconfig`
- [ ] Coding standards / release convention
- [ ] Development setup documentation
- [ ] Production deployment documentation
- [ ] DB backup / rollback / smoke-test documentation
- [!] Re-run production deployment and verify Composer/DB output
- [!] Full production admin CRUD smoke test

# 2. Users, roles & security
- [x] Administrator / Editor / Writer / SEO Manager roles
- [x] Explicit role capability helpers
- [x] Role-aware admin navigation
- [x] Session authentication, password hashing and logout
- [x] CSRF protection
- [x] Secure/HttpOnly/SameSite cookies
- [x] Session rotation and idle timeout
- [x] Login throttling and failed-login tracking
- [x] Last-login tracking
- [x] User create/edit/activate/deactivate safeguards
- [x] Logged-in password change
- [x] CSP and baseline response security headers
- [x] Rich-content HTML sanitization
- [x] Admin audit-log storage and administrator audit screen
- [~] Complete audit coverage on every mutation path
- [ ] Self-service forgotten-password/reset workflow

# 3. Categories, brands & products
## Categories
- [x] Nested categories, SEO, images, sort order and archive/restore
- [x] Category admin create/edit
- [x] Public category pages
- [x] Pagination, sorting and brand/price/score/spec filters
- [x] Nested breadcrumbs + BreadcrumbList schema
- [x] Automatic redirects on category slug changes

## Brands
- [x] Brand create/edit
- [x] Website/logo validation
- [x] Media-library logo picker
- [ ] Brand archive/restore
- [ ] Public brand pages if useful

## Products
- [x] Manual / Amazon API / hybrid source model
- [x] Product admin create/edit/preview
- [x] Slug generation + duplicate-slug validation
- [x] ASIN validation + duplicate prevention
- [x] Archive/restore/duplicate actions
- [x] Product media picker and gallery editor
- [x] Flexible specifications
- [x] Public product page, schema, gallery and related content
- [x] Amazon-price freshness enforcement
- [ ] Product change-history view
- [ ] Bulk product actions
- [ ] CSV import/export

# 4. Flexible specifications
- [x] Category-specific definitions
- [x] Text / number / boolean / select types
- [x] Units and select options
- [x] Filterable/comparable flags
- [x] Dynamic product editing and validation
- [x] Public product spec table
- [x] Category spec filters
- [x] Comparison spec matrix
- [x] Archive/restore while preserving values

# 5. Storefront & navigation
- [x] Responsive storefront foundation
- [x] Header/footer/search/disclosure
- [x] Homepage categories/products/guides/comparisons/articles
- [x] Website settings and homepage section toggles
- [x] Curated Featured and Deals product merchandising
- [ ] Mobile navigation polish
- [ ] Accessibility/keyboard/skip-link/focus-state review
- [ ] Final visual QA against MediaPitch branding

# 6. Buying guides
- [x] Guide CRUD, scheduling and SEO
- [x] Reusable product relationships and ranking data
- [x] Searchable product picker
- [x] Drag-and-drop ranking + automatic renumbering
- [x] Duplicate-product prevention
- [x] Public ranked guide output, schema and breadcrumbs
- [x] Related editorial content
- [ ] FAQ editor/output
- [ ] “How we selected” structured section
- [ ] Table of contents

# 7. Blog / editorial CMS
- [x] Blog CRUD, category, scheduling and SEO
- [x] Media picker and safe public HTML
- [x] Blog index/article route, schema, breadcrumbs and OG image
- [x] Related editorial content
- [ ] Rich-text/Markdown editing UX
- [ ] Tags
- [ ] Product embeds

# 8. Comparisons & reviews
## Comparisons
- [x] Comparison CRUD and 2+ product selection
- [x] Dynamic category-aware specification matrix
- [x] Public index/detail pages
- [x] Mobile comparison-table improvements
- [x] Related editorial content
- [ ] Decide/add comparison-specific structured data where appropriate

## Reviews
- [x] Review CRUD, product relationship and rating
- [x] Public review route and Review schema
- [x] Media picker, breadcrumbs and safe body rendering
- [x] Related editorial content
- [ ] Additional related-product recommendations

# 9. Search & filtering
- [x] Unified product/category/guide/comparison/review/blog search
- [x] Product-result pagination
- [x] Autocomplete suggestions API/UI
- [x] Global category search filter
- [x] Privacy-light search query analytics
- [x] Top searches / zero-result / category search reporting in admin

# 10. Affiliate links & analytics
- [x] `/go/{product}` affiliate redirect
- [x] Product/content/rank/CTA/referrer/user-agent/campaign click tracking
- [x] Admin date-range affiliate reporting
- [x] Product/content/CTA/rank/campaign breakdowns
- [x] Daily click trend and CSV export
- [x] One-hour freshness enforcement for Amazon API offer prices
- [~] Complete Amazon Associates/Creators API compliance review
- [ ] Bot/internal click filtering
- [ ] Privacy/data-retention policy

# 11. Media library
- [x] Upload, MIME validation, size limits and randomized paths
- [x] Dimension/uploader/alt-text metadata
- [x] Media browser/search and reusable pickers
- [x] WebP thumbnails when GD supports them
- [x] Usage checks and safe deletion
- [ ] Original-image optimization/compression policy
- [ ] Storage abstraction for future S3/R2

# 12. SEO
- [x] SEO titles/meta/canonical/index controls
- [x] `|` title separator
- [x] Open Graph/Twitter metadata and dynamic images
- [x] Sitemap and robots.txt
- [x] Product/BlogPosting/Review/Buying-guide structured data
- [x] Breadcrumb UI/schema
- [x] Redirect manager and runtime redirects
- [x] Automatic redirects on product/content/category slug changes
- [ ] SEO preview in editors
- [ ] Internal-link helper beyond automatic related-content sections
- [ ] Comparison structured-data decision

# 13. Amazon Creators API
- [x] Optional integration boundary; manual CMS remains independent
- [x] Current OAuth/Creators API flow implemented
- [x] Encrypted administrator-only credentials
- [x] Authentication test and status fields
- [x] OAuth token reuse until near expiry
- [x] SearchItems / GetItems import flow
- [x] New imports arrive inactive for editorial review
- [x] Re-import by ASIN rather than duplication
- [x] Preserve editorial/manual fields on refresh
- [x] Refresh Amazon-owned title/price/link/sync fields
- [x] One-hour public offer-price freshness rule
- [~] Finish full Associates policy review
- [ ] API retry/backoff
- [ ] Bulk/background refresh workflow
- [ ] Better API health / per-product last-sync UX
- [ ] Field-level manual override controls
- [ ] Multiple marketplaces

# 14. Lower-priority/future capabilities
- [ ] Newsletter / personalization / alerts
- [ ] Additional affiliate networks
- [ ] AI-assisted product/guide/scoring/content workflows

---

# Immediate execution queue
1. [!] Redeploy and verify automatic migration/seed output on production
2. [~] Complete admin audit coverage
3. [ ] Self-service password reset
4. [ ] Amazon retry/backoff + bulk refresh + sync UX
5. [ ] Rich-text/Markdown editor + product embeds
6. [ ] Brand archive/restore
7. [ ] Mobile/accessibility/final visual polish
8. [ ] Product CSV/bulk tooling
9. [ ] Production/development/backup documentation
