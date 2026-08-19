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
- CMS Admin API v1 is live and authenticated; full control surface is implemented on `main`
- Deployment DB flow: schema -> retry-safe repair migrations -> schema verification -> non-destructive seeds -> bootstrap-admin sync
- Production schema self-heal/repair path exists for previously incomplete deployments
- Public titles use `|` separators

## Foundation / deployment
- [x] PHP 8.2 app, PDO/MariaDB, `.env`, autoloading
- [x] Root/public routing compatibility and `/health`
- [x] GitHub Actions PHP lint
- [x] Schema bootstrap + retry-safe migrations
- [x] Comprehensive repair migrations for incomplete production schema
- [x] Schema verification before seeding
- [x] Non-destructive seeds
- [x] Environment-driven bootstrap administrator recovery
- [x] Composer DB deployment hook fails loudly on production DB/migration failure
- [x] Dev/deploy/release/backup/rollback docs
- [x] Smoke test checks migration-owned tables and critical columns
- [!] Run latest `composer deploy-db` / `composer smoke-test` on production and retain successful output
- [!] Run full production admin CRUD smoke test

## Users / roles / security
- [x] Administrator / Editor / Writer / SEO Manager permissions
- [x] Auth, CSRF, password hashing, secure cookies, session rotation/idle timeout
- [x] Login throttling, failed-login and last-login tracking
- [x] User CRUD safeguards
- [x] Change password + forgot/reset password
- [x] Timed password reveal controls
- [x] CSP/security headers + HTML sanitization
- [x] Audit log and major mutation coverage
- [x] Bootstrap login recovery tolerates legacy production user schemas

## Catalog
- [x] Nested categories + filters/pagination/breadcrumbs/redirects
- [x] Brand CRUD, logo/media, archive/restore
- [x] Public brand repository/view/route
- [x] Product CRUD, slug/ASIN validation, archive/restore/duplicate
- [x] Product gallery, specifications and public product page/schema
- [x] Bulk actions + validated CSV import/export
- [x] Product change history
- [x] Product Amazon override flags persist only after successful product save

## Buying guides / blog / editorial
- [x] Guide CRUD/scheduling/SEO/product ranking
- [x] Searchable guide product picker + drag ordering
- [x] FAQ / How we selected helpers + automatic H2/H3 TOC
- [x] Blog CRUD/scheduling/SEO/media/schema
- [x] Safe rich-text toolbar + product embeds
- [x] Blog tags + public display + BlogPosting keywords
- [x] Blog tag handling works without requiring ext-mbstring
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

## CMS documentation / user help
- [x] Dedicated Documentation tab in the authenticated CMS sidebar
- [x] Searchable user-facing handbook
- [x] Role-aware help: only explains features the logged-in role can access
- [x] Step-by-step workflows for Products, Categories, Brands, Specifications, Media and Homepage Picks
- [x] Step-by-step workflows for Blog, Buying Guides, Reviews and Comparisons
- [x] Administrator help for Redirects, Website Settings, Amazon, Users, Analytics and Audit Log
- [x] Plain-language SEO field guide, publishing checklist and troubleshooting section
- [x] Visual UI walkthrough diagrams for Product, Blog, Guide and Amazon workflows
- [!] Add/replace with real production CMS screenshots after latest build is deployed and visually verified

## CMS Admin API / agent control
- [x] Authenticated `/api/v1` control surface
- [x] Bearer and X-API-Key authentication
- [x] Optional query-key + GET command mode for constrained clients
- [x] Idempotent GET writes via `request_id`
- [x] Product/category/brand/guide reads and writes
- [x] Blog reads and `blog.save`
- [x] Review reads and `review.save`
- [x] Comparison reads and `comparison.save`
- [x] Redirect reads and `redirect.save`
- [x] Media reads, alt-text updates and category-image assignment
- [x] Specification reads/save/archive/restore
- [x] Site settings reads and patch-safe save
- [x] Homepage merchandising reads and patch-safe save
- [x] Safe Amazon profile/status reads, search, activate, test, refresh and ASIN import
- [x] Amazon credentials never returned by the API and credential management stays in administrator UI
- [x] Existing-record API updates preserve omitted editable fields
- [x] No raw SQL or arbitrary command endpoint
- [x] API writes are audited
- [!] Verify expanded API endpoints after latest `main` is deployed to production
- [ ] Replace query-string API-key mode with a safer agent-compatible transport when an arbitrary authenticated HTTP connector is available

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
- [x] Marketplace-neutral public Amazon pricing disclosure
- [x] Multiple encrypted marketplace profiles + active-profile admin UI
- [x] Marketplace-aware ASIN import/validation
- [x] Scheduled refresh iterates every enabled configured marketplace
- [x] Legacy unscoped Amazon products are only auto-claimed when exactly one configured marketplace exists
- [x] Associates/Creators API implementation guardrails documented from current official Amazon requirements
- [!] Configure/verify production Amazon refresh cron if Amazon integration is enabled
- [!] Verify each intended marketplace with real Creators API credentials before enabling it in production

## Storefront
- [x] Responsive layout + homepage merchandising/settings
- [x] Mobile nav, skip links, focus states, reduced motion
- [!] Final visual QA against live MediaPitch branding

## Optional / future scope
- [ ] Newsletter / personalization / alerts
- [ ] Additional affiliate networks
- [ ] AI-assisted product/guide/scoring/content workflows beyond the admin API control surface
- [ ] S3/R2 media storage adapter if local storage stops being sufficient
- [ ] Agent-compatible authenticated HTTP transport that can replace temporary query-key GET mode

## Immediate execution queue
1. [!] Deploy latest `main`, run `composer deploy-db`, then `composer smoke-test`
2. [!] Verify the Documentation tab and capture real CMS screenshots for the handbook
3. [!] Verify the expanded CMS Admin API endpoints/commands on production
4. [!] Run live admin CRUD smoke test across Products, Categories, Brands, Media, Blog, Guides, Reviews, Comparisons, Audit and Settings
5. [!] Configure/verify Amazon refresh cron and real marketplace credentials if Amazon integration is enabled
6. [!] Final public/admin visual QA

**Code-side MediaPitch Store v1 is complete.** Remaining immediate items are production deployment/sign-off checks rather than missing MVP implementation.
