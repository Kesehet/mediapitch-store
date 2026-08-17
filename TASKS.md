# MediaPitch Store | Project Task Tracker

This file is the source of truth for work landing on `main`.

## Status
- [x] Done
- [~] In progress
- [ ] Remaining
- [!] Needs production/external verification

## Current snapshot
- Production branch: `main`
- Production URL: `https://store.mediapitch.in/`
- Production `/health`: DB connection confirmed OK from the user's browser
- Recent PHP syntax GitHub Actions: passing
- Deployment DB flow: schema -> retry-safe migrations -> non-destructive seeds -> optional bootstrap-admin recovery
- Bootstrap admin recovery uses `BOOTSTRAP_ADMIN_PASSWORD` only before first successful login
- Public titles use `|` separators

## Foundation / deployment
- [x] PHP 8.2 app, PDO/MariaDB, `.env`, autoloading
- [x] Root/public routing compatibility and `/health`
- [x] GitHub Actions PHP lint
- [x] Schema bootstrap + retry-safe migrations
- [x] Non-destructive seeds
- [x] Bootstrap admin + safe pre-first-login recovery
- [x] Composer DB deployment hook
- [x] Dev/deploy/release/backup/rollback docs
- [!] Verify latest production deployment output
- [!] Run full production CRUD smoke test

## Users / roles / security
- [x] Administrator / Editor / Writer / SEO Manager permissions
- [x] Auth, CSRF, password hashing, secure cookies, session rotation/idle timeout
- [x] Login throttling, failed-login and last-login tracking
- [x] User CRUD safeguards
- [x] Change password + forgot/reset password
- [x] Timed password reveal controls
- [x] CSP/security headers + HTML sanitization
- [x] Audit log and major mutation coverage

## Catalog
- [x] Nested categories + filters/pagination/breadcrumbs/redirects
- [x] Brand CRUD, logo/media, archive/restore
- [x] Public brand repository/view/route
- [x] Product CRUD, slug/ASIN validation, archive/restore/duplicate
- [x] Product gallery, specifications and public product page/schema
- [x] Bulk actions + validated CSV import/export
- [x] Product change history

## Buying guides / blog / editorial
- [x] Guide CRUD/scheduling/SEO/product ranking
- [x] Searchable guide product picker + drag ordering
- [x] FAQ / How we selected helpers + automatic H2/H3 TOC
- [x] Blog CRUD/scheduling/SEO/media/schema
- [x] Safe rich-text toolbar + product embeds
- [x] Blog tags + public display + BlogPosting keywords
- [x] Searchable internal-link editor helper

## Comparisons / reviews
- [x] Comparison CRUD/spec matrix/public index/detail/mobile table
- [x] Comparison Article + ItemList + Breadcrumb structured data
- [x] Review CRUD + Review schema
- [x] Related editorial content + related-product recommendations

## Search / analytics
- [x] Unified search + category filter + pagination + autocomplete
- [x] Privacy-light search analytics and admin reports
- [x] Affiliate redirect/click analytics/reporting/CSV
- [x] Known bot/headless + configurable internal IP/UA filtering
- [x] Configurable analytics/audit retention + pruning command

## Media
- [x] Upload/MIME/size validation + randomized paths
- [x] Media search/pickers/metadata/usage checks/safe deletion
- [x] WebP thumbnails when GD is available
- [x] Configurable original resizing/compression; original retained when optimization is not beneficial
- [x] Media storage interface + local implementation ready for future S3/R2 adapter

## SEO
- [x] SEO/meta/canonical/index controls
- [x] OG/Twitter + dynamic images
- [x] Sitemap/robots/breadcrumbs/redirects
- [x] Structured data for products/blog/reviews/guides/comparisons
- [x] Live SEO preview
- [x] Searchable internal-link editor helper

## Amazon Creators API
- [x] Optional integration; manual CMS remains independent
- [x] OAuth/encrypted credentials/test/token reuse
- [x] SearchItems/GetItems import and ASIN re-import
- [x] Retry/backoff + bulk stale-product refresh
- [x] Inactive-draft imports
- [x] Per-product sync status + field-level manual overrides
- [x] One-hour offer-price freshness enforcement
- [x] Hourly/daily CLI refresh path + deployment cron documentation
- [x] Fixed Amazon pricing/availability disclosure + price Details links
- [x] Associates/Creators API implementation guardrails documented from current official Amazon requirements
- [!] Configure/verify production Amazon refresh cron if Amazon integration is enabled
- [ ] Multiple marketplaces (lower priority)

## Storefront
- [x] Responsive layout + homepage merchandising/settings
- [x] Mobile nav, skip links, focus states, reduced motion
- [ ] Final visual QA against live MediaPitch branding

## Lower-priority / future
- [ ] Newsletter / personalization / alerts
- [ ] Additional affiliate networks
- [ ] AI-assisted product/guide/scoring/content workflows

## Immediate execution queue
1. [!] Verify latest production deployment/bootstrap recovery output
2. [!] Run full production admin CRUD smoke test
3. [!] Configure/verify hourly Amazon refresh cron if integration is enabled
4. [ ] Final visual QA on live storefront/admin
5. [ ] Multiple Amazon marketplaces (lower priority)
