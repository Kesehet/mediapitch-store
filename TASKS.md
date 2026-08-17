# MediaPitch Store — Project Task Tracker

This file is the project source of truth. Update it as work lands on `main`.

## Status legend
- [x] Done
- [ ] Remaining
- [~] In progress
- [!] Blocked / needs production or external input

## Current snapshot
- Production branch: `main`
- Production URL: `https://store.mediapitch.in/`
- Development mode: rapid iteration directly on `main`
- Production `/health`: DB connection confirmed OK
- Database deploy flow: base schema → migrations → non-destructive defaults
- Bootstrap admin: created only when user count is zero
- Amazon API: optional; manual CMS remains fully usable without it

---

# 1. Foundation & deployment
- [x] PHP application structure
- [x] MariaDB/PDO layer
- [x] Environment loader and `.env.example`
- [x] `.env` ignored
- [x] Autoloader and view renderer
- [x] Public front controller
- [x] Root `index.php` compatibility entrypoint
- [x] Apache rewrite rules in `public/`
- [x] Root `.htaccess` compatibility bridge
- [x] `/health` endpoint
- [x] 404/500 pages
- [x] PHP 8.2 syntax workflow
- [x] Landing-page DB fallback
- [x] Migration runner and migration tracking
- [x] Non-destructive default seed script
- [x] Automatic DB deployment via Composer scripts
- [x] Base schema bootstrap for empty DB
- [x] Seed defaults only when missing
- [x] Seed bootstrap admin only when user count is zero
- [x] Production DB connectivity verified through `/health`
- [x] URL helper falls back to current production host if `APP_URL` is localhost/missing
- [ ] `.editorconfig`
- [ ] Coding-standard documentation
- [ ] Version/release convention
- [ ] Development environment documentation
- [ ] Production deployment documentation
- [ ] Verify full admin CRUD flow against production DB
- [ ] DB backup/rollback/smoke-test documentation

---

# 2. Users, roles & security
- [x] Users table and active flag
- [x] Administrator / Editor / Writer / SEO Manager roles
- [x] Session login/logout
- [x] Password hashing
- [x] CSRF protection
- [x] Role helpers
- [x] CLI first-admin creation
- [x] Bootstrap admin seed for empty user table
- [x] User admin list/create/edit
- [x] User activation/deactivation safeguards
- [x] Prevent removal of last active administrator
- [x] Logged-in user password-change screen
- [x] Login rate limiting
- [x] Persistent failed-login counters/timestamp
- [x] Last-login timestamp
- [x] Session rotation and idle timeout
- [x] Secure/HttpOnly/SameSite production cookie settings
- [x] Baseline CSP/security headers
- [x] Rich-content XSS sanitizer for public HTML output
- [ ] Password-reset / forgotten-password workflow
- [ ] Full role permission matrix
- [ ] Admin audit trail

---

# 3. Categories, brands & products
## Categories
- [x] Category model, nesting, slug, description, image URL, SEO, sort order, active flag
- [x] Admin list/create/edit
- [x] Public `/category/{slug}`
- [x] Category product / guide / article sections
- [x] Category canonical metadata
- [x] Category image assignment from Media Library
- [x] Category product pagination
- [x] Category brand / price / score / sort filters
- [x] Category dynamic specification filters
- [ ] Nested breadcrumb hierarchy
- [ ] Archive/delete

## Brands
- [x] Brand model
- [x] Admin list/create/edit
- [x] Website/logo URL fields
- [x] Product-brand relationship
- [x] Website/logo URL validation
- [x] Brand logo media picker
- [ ] Archive/delete
- [ ] Public brand pages if useful

## Products
- [x] Central reusable product model
- [x] Manual / Amazon API / hybrid source
- [x] ASIN, category, brand, title/display title, slug
- [x] Main image URL and gallery storage field
- [x] Descriptions, features, pros/cons
- [x] Price/previous price/currency
- [x] Amazon URL and affiliate URL
- [x] MediaPitch score / best-for / editorial notes
- [x] Manual override metadata / sync timestamp / active flag
- [x] Admin list/create/edit
- [x] Product preview
- [x] Public `/product/{slug}`
- [x] Product media-library picker
- [x] Default MediaPitch/Fill Masjid seed data
- [x] Product page escapes manual full-description text
- [x] Slug auto-generation in UI and backend
- [x] Friendly duplicate-slug validation
- [x] ASIN normalization/validation and duplicate protection
- [x] Archive/restore product action
- [x] Duplicate-product action with copied specs and safe identity reset
- [ ] Gallery editor
- [ ] Related products / buying guides
- [ ] Change history / bulk actions / CSV import-export

---

# 4. Flexible specifications
- [x] Category-specific specification definitions
- [x] Text / number / boolean / select types
- [x] Units, select options, filterable/comparable flags, sort order
- [x] Admin CRUD
- [x] Dynamic product fields by category
- [x] Type validation and persistence
- [x] Public product specification table
- [x] Comparison pages consume comparable specs
- [x] Public category filtering using filterable specs
- [ ] Spec archive/delete
- [ ] Better select-option UX

---

# 5. Storefront & navigation
- [x] MediaPitch-oriented branding and responsive base layout
- [x] Header/footer and affiliate disclosure
- [x] Hero search
- [x] Featured categories/products/guides/articles
- [x] Homepage category/blog links
- [x] Latest comparisons homepage section
- [x] Homepage section controls in admin
- [x] Site name/tagline/disclosure settings
- [ ] Deals/featured-products merchandising section
- [ ] Mobile navigation polish
- [ ] Accessibility/keyboard/skip-link/focus-state review
- [ ] Final visual QA against MediaPitch brand

---

# 6. Buying guides
- [x] Buying-guide content type and reusable product relationships
- [x] Per-guide rank, score, best-for, recommendation and CTA
- [x] Admin list/create/edit
- [x] SEO fields and scheduling model
- [x] Transactional product relationship save
- [x] Public guide route and ranked product output
- [x] Affiliate rank/context tracking
- [x] Guide media picker
- [x] Buying-guide Article schema
- [x] Buying-guide breadcrumb UI/schema
- [ ] Product autocomplete/search in editor
- [ ] Drag-and-drop ranking
- [ ] Better duplicate-product UX
- [ ] FAQ / “How we selected” / table of contents
- [ ] Related guides/articles/comparisons

---

# 7. Blog / editorial CMS
- [x] Admin list/create/edit
- [x] Category, image URL, excerpt/body
- [x] Draft/scheduled/published states and publish date
- [x] SEO title/meta/canonical/index controls
- [x] Public `/blog` and `/blog/{slug}`
- [x] Author/category/date display
- [x] Blog media picker
- [x] BlogPosting structured data
- [x] Article breadcrumb UI/schema
- [x] Sanitized rich-content public output
- [x] Dynamic OG image output
- [ ] Rich-text/Markdown editing UX
- [ ] Tags
- [ ] Product embeds
- [ ] Related articles

---

# 8. Comparisons & reviews
## Comparisons
- [x] Admin list/create/edit
- [x] Select/order 2+ products
- [x] Duplicate-product protection
- [x] Editorial verdict and scheduling/SEO fields
- [x] Public `/compare/{slug}`
- [x] Public `/comparisons` index
- [x] Category-aware dynamic spec comparison matrix
- [x] Brand/score/best-for/price rows
- [x] Affiliate CTA context tracking
- [x] Sanitized verdict output
- [x] Improved mobile table UX with sticky feature column
- [ ] Related comparisons
- [ ] Comparison structured data if appropriate

## Reviews
- [x] Review content type
- [x] Admin review list/editor
- [x] Review-product relationship
- [x] Rating/score UI
- [x] Public `/review/{slug}` route
- [x] Review media picker
- [x] Review structured data
- [x] Review breadcrumb UI/schema
- [x] Sanitized review body output
- [ ] Related reviews/products

---

# 9. Search & filtering
- [x] Search route and grouped UI
- [x] Products with brand/category matching
- [x] Categories
- [x] Buying guides
- [x] Comparisons
- [x] Blog articles
- [x] Reviews rendered in unified search UI
- [x] Search-result pagination foundation
- [x] Search suggestions JSON endpoint
- [x] Header/search autocomplete UI
- [x] Category product pagination
- [x] Category sorting
- [x] Brand / price / score filters on category pages
- [x] Dynamic specification filters on category pages
- [ ] Global category filter on search results
- [ ] Search analytics

---

# 10. Affiliate links & analytics
- [x] Central affiliate URL
- [x] `/go/{product}` redirect
- [x] Click tracking: product, content, rank, CTA, referrer, user agent, campaign
- [x] Dashboard total clicks / top products
- [x] Comparison CTA tracking
- [x] Administrator affiliate analytics dashboard
- [x] Date-range reports
- [x] Product/content/CTA/rank/campaign breakdowns
- [x] Daily click trend
- [x] CSV export
- [ ] Verify redirect/link behavior against current Amazon policy
- [ ] Bot/internal filtering
- [ ] Privacy/data-retention review

---

# 11. Media library
- [x] Media DB migration
- [x] Migration runner support
- [x] Upload endpoint
- [x] JPEG / PNG / WebP / GIF MIME validation
- [x] 5 MB image-size limit
- [x] Randomized storage filenames
- [x] Image dimension capture
- [x] Uploader attribution
- [x] Media browser
- [x] Alt-text capture
- [x] Product/guide/blog/review/category media pickers
- [x] Brand logo picker
- [x] WebP thumbnail generation when available
- [x] Graceful fallback without image-processing extensions
- [x] Media search by filename/alt text
- [x] Usage checks across products/categories/brands/content/galleries
- [x] Safe delete only when unused
- [x] Delete original + thumbnail files after DB removal
- [ ] Original-image optimization/compression policy
- [ ] Storage abstraction for future S3/R2

---

# 12. SEO
- [x] SEO titles/meta descriptions
- [x] Canonical/index support across product/category/guide/blog/comparison/review
- [x] Open Graph and Twitter metadata
- [x] Dynamic OG/Twitter images per supported public route
- [x] Dynamic `/sitemap.xml`
- [x] Sitemap includes products/categories/guides/blog/comparisons/reviews
- [x] `robots.txt`
- [x] Product structured data
- [x] BlogPosting structured data
- [x] Review structured data
- [x] Buying-guide Article structured data
- [x] Product/article/review/guide breadcrumb UI + schema
- [ ] Category breadcrumb schema
- [ ] Comparison structured data if appropriate
- [ ] Redirect admin UI
- [ ] Auto-redirect when published slug changes
- [ ] SEO preview in editors
- [ ] Internal-link helper

---

# 13. Amazon Creators API
## Architecture & compliance
- [x] Amazon provider boundary
- [x] Manual workflow independent of API
- [x] Source abstraction and sync fields
- [x] Current Creators API authentication/documentation reviewed
- [ ] Complete Associates policy review for images, price freshness, caching/storage and disclosure

## Settings
- [x] Administrator-only Amazon settings page
- [x] Marketplace / partner tag / credential ID / secret / version
- [x] Enabled toggle / authentication test / last success/error
- [x] Encrypted secret storage using `APP_KEY`
- [x] Credentials excluded from public output

## Import & sync
- [ ] Products → Import from Amazon
- [ ] Product search/results/import
- [ ] Map currently permitted fields
- [ ] Store affiliate-attributed URL where supported
- [ ] Preserve editorial/manual fields
- [ ] Refresh imported data and field-level override strategy
- [ ] API health / retry / last-sync UX

---

# 14. Settings & future work
- [x] Website settings page
- [x] Site name/tagline/global affiliate disclosure
- [x] Homepage section controls
- [x] Detailed click analytics dashboard/export
- [ ] Redirect manager
- [ ] Multiple marketplaces
- [ ] Newsletter/personalization/alerts
- [ ] Other affiliate networks
- [ ] AI product/guide/scoring/content-assistance features

---

# Immediate next queue
1. [~] Build redirect manager and automatic redirects when slugs change
2. [ ] Add category archive/delete and nested breadcrumb hierarchy
3. [ ] Add specification archive/delete and improve select-option UX
4. [ ] Add product gallery editor
5. [ ] Add related products/guides/articles/comparisons
6. [ ] Improve guide product picker/ranking UX
7. [ ] Finish Amazon policy review, then product search/import/sync
8. [ ] Verify full admin CRUD flow on production
