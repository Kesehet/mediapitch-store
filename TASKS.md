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
- Amazon API: optional; manual CMS must remain fully usable without it
- Homepage degrades gracefully when the DB is unavailable
- GitHub Actions PHP syntax checks run on pushes to `main`
- Default seed products: Know Your Prophets, The Path of the Caliphs, Growing With Adab

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
- [x] Simple migration runner (`database/migrate.php`)
- [x] Migration tracking table created by runner
- [x] Idempotent default seed script (`database/seed-defaults.php`)
- [ ] `.editorconfig`
- [ ] Coding-standard documentation
- [ ] Version/release convention
- [ ] Development environment documentation
- [ ] Production deployment documentation
- [!] Confirm production PHP/PDO/MySQL configuration
- [!] Configure production `.env`
- [!] Create/confirm production database
- [!] Import `database/schema.sql`
- [!] Run `php database/migrate.php`
- [!] Run `php database/seed-defaults.php`
- [!] Create first production administrator
- [ ] Verify `/health` returns DB OK
- [ ] Verify admin CRUD against production DB
- [ ] Add DB backup/rollback/smoke-test documentation

---

# 2. Users, roles & security
- [x] Users table and active flag
- [x] Administrator / Editor / Writer / SEO Manager roles
- [x] Session login/logout
- [x] Password hashing
- [x] CSRF protection
- [x] Role helpers
- [x] CLI first-admin creation
- [ ] User CRUD
- [ ] Password change/reset
- [ ] Login rate limiting
- [ ] Failed-login tracking
- [ ] Session rotation and idle timeout
- [ ] Secure production cookie settings
- [ ] Full role permission matrix
- [ ] Admin audit trail
- [ ] CSP/security headers
- [ ] Rich-content XSS review

---

# 3. Categories, brands & products
## Categories
- [x] Category model, nesting, slug, description, image URL, SEO, sort order, active flag
- [x] Admin list/create/edit
- [x] Public `/category/{slug}`
- [x] Category product / guide / article sections
- [x] Category canonical metadata
- [ ] Nested breadcrumbs
- [ ] Archive/delete
- [ ] Category media picker
- [ ] Pagination and filters

## Brands
- [x] Brand model
- [x] Admin list/create/edit
- [x] Website/logo URL fields
- [x] Product-brand relationship
- [ ] URL validation UX
- [ ] Brand logo media picker
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
- [x] Default MediaPitch/Fill Masjid product seed data
- [ ] Slug auto-generation
- [ ] Friendly duplicate-slug validation
- [ ] Duplicate ASIN warning
- [ ] Archive/delete and duplicate-product action
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
- [ ] Dynamic public filters using filterable specs
- [ ] Spec archive/delete
- [ ] Better select-option UX

---

# 5. Storefront & navigation
- [x] MediaPitch-oriented branding and responsive base layout
- [x] Header/footer and affiliate disclosure
- [x] Hero search
- [x] Featured categories/products/guides/articles
- [x] Homepage links to category/blog routes
- [ ] Trending comparisons section
- [ ] Deals/featured products section
- [ ] Homepage controls in admin
- [ ] Mobile nav polish
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
- [ ] Product autocomplete/search in editor
- [ ] Drag-and-drop ranking
- [ ] Better duplicate-product UX
- [ ] FAQ / “How we selected” / table of contents
- [ ] Related guides/articles/comparisons
- [ ] Buying-guide schema and breadcrumbs
- [ ] Guide media picker

---

# 7. Blog / editorial CMS
- [x] Admin list/create/edit
- [x] Category, image URL, excerpt/body
- [x] Draft/scheduled/published states and publish date
- [x] SEO title/meta/canonical/index controls
- [x] Public `/blog` and `/blog/{slug}`
- [x] Author/category/date display
- [ ] Safe Markdown or rich-text editor
- [ ] Tags
- [ ] Product embeds
- [ ] Related articles
- [ ] Blog media picker
- [ ] Article structured data / OG image

---

# 8. Comparisons & reviews
## Comparisons
- [x] Admin list/create/edit
- [x] Select/order 2+ products
- [x] Duplicate-product protection
- [x] Editorial verdict and scheduling/SEO fields
- [x] Public `/compare/{slug}`
- [x] Category-aware dynamic spec comparison matrix
- [x] Brand/score/best-for/price rows
- [x] Affiliate CTA context tracking
- [ ] Comparison index
- [ ] Related comparisons
- [ ] Better mobile table UX
- [ ] Comparison structured data if appropriate

## Reviews
- [x] Review content type exists
- [ ] Admin review editor
- [ ] Review-product relationship
- [ ] Rating/score UI
- [ ] Public review route
- [ ] Review structured data

---

# 9. Search & filtering
- [x] Search route and grouped UI
- [x] Products with brand/category matching
- [x] Categories
- [x] Buying guides
- [x] Comparisons
- [x] Blog articles
- [ ] Reviews once review pages exist
- [ ] Pagination/sorting
- [ ] Brand / price / score / category filters
- [ ] Dynamic specification filters
- [ ] Search analytics
- [ ] Autocomplete/suggestions

---

# 10. Affiliate links & analytics
- [x] Central affiliate URL
- [x] `/go/{product}` redirect
- [x] Click tracking: product, content, rank, CTA, referrer, user agent, campaign
- [x] Dashboard total clicks / top products
- [x] Comparison CTA tracking
- [ ] Verify redirect/link behavior against current Amazon policy
- [ ] Bot/internal filtering
- [ ] Date/guide/comparison/CTA/rank/campaign reports
- [ ] CSV export
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
- [x] Product media picker
- [ ] Image optimization
- [ ] Thumbnail generation
- [ ] Media search
- [ ] Category picker
- [ ] Guide/blog picker
- [ ] Safe delete / usage checks
- [ ] Storage abstraction for future S3/R2

---

# 12. SEO
- [x] SEO titles/meta descriptions
- [x] Canonical/index support across product/category/guide/blog/comparison
- [x] Base Open Graph title/description
- [x] Dynamic `/sitemap.xml`
- [x] Sitemap includes products/categories/guides/blog/comparisons/reviews
- [x] `robots.txt`
- [ ] Dynamic OG images
- [ ] Twitter cards
- [ ] Breadcrumb UI + schema
- [ ] Product/article/review/buying-guide structured data
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
- [ ] Verify current Creators API documentation
- [ ] Verify Associates policy, image, price/freshness, caching/storage and disclosure rules

## Settings
- [ ] Amazon settings admin page
- [ ] Marketplace / partner tag / credential ID / secret / version
- [ ] Enabled toggle / connection test / last success/error
- [ ] Encrypt secrets and prevent logging/frontend exposure

## Import & sync
- [ ] Products → Import from Amazon
- [ ] Product search/results/import
- [ ] Map currently permitted fields
- [ ] Store affiliate-attributed URL where supported
- [ ] Preserve editorial/manual fields
- [ ] Refresh imported data and field-level override strategy
- [ ] API health / retry / last-sync UX

---

# 14. Settings, analytics & future work
- [ ] Website settings page
- [ ] Site name/tagline/global affiliate disclosure
- [ ] Homepage section controls
- [ ] Redirect manager
- [ ] Detailed click analytics dashboard
- [ ] Multiple marketplaces
- [ ] Newsletter/personalization/alerts
- [ ] Other affiliate networks
- [ ] AI product/guide/scoring/content-assistance features

---

# Immediate next queue
1. [~] Extend media picker to category, buying-guide and blog editors
2. [ ] Add image optimization + thumbnail generation
3. [ ] Build review CMS + public review pages
4. [ ] Add product/category filtering and pagination
5. [ ] Add structured data + breadcrumbs
6. [ ] Build Amazon settings page and verify current Creators API policy/docs
7. [!] Configure production DB, run migrations/seeds and verify seeded products live
