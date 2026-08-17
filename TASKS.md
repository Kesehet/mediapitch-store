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
- Homepage: degrades gracefully if the production database is unavailable
- Production database/runtime still needs final configuration and verification
- GitHub Actions PHP syntax checks are running on pushes to `main`

---

# 1. Project foundation

- [x] PHP application structure
- [x] MariaDB/PDO data layer
- [x] Environment loader
- [x] `.env.example`
- [x] `.env` ignored from Git
- [x] Autoloader
- [x] View renderer
- [x] Public front controller
- [x] Root compatibility entrypoint
- [x] Apache rewrite rules in `public/`
- [x] Root `.htaccess` compatibility bridge
- [x] `/health` endpoint
- [x] 404 and 500 pages
- [x] README setup instructions
- [x] PHP 8.2 syntax-check workflow
- [x] Landing page fallback when DB is unavailable
- [ ] `.editorconfig`
- [ ] Coding-standard documentation
- [ ] Database migration/versioning strategy
- [ ] Seed-data strategy
- [ ] Version/release convention
- [ ] Development environment documentation
- [ ] Production deployment documentation

---

# 2. Production & deployment

- [x] Merge active CMS work into `main`
- [x] Add root entrypoint for hosts serving repository root
- [x] Add root rewrite/security rules
- [x] Prevent landing page DB failure from crashing entire page
- [!] Confirm production web server / hosting configuration
- [!] Confirm production document root
- [!] Confirm PHP version
- [!] Confirm PDO MySQL extension
- [!] Configure production `.env`
- [!] Create/confirm production MariaDB database
- [!] Import `database/schema.sql`
- [!] Create first production administrator
- [ ] Verify `/health` returns DB OK
- [ ] Verify homepage with live DB content
- [ ] Verify `/admin/login`
- [ ] Verify create/edit product in production
- [ ] Verify create/edit guide in production
- [ ] Verify affiliate redirect route
- [ ] Add rollback procedure
- [ ] Add DB backup procedure
- [ ] Add deployment smoke-test checklist
- [ ] Document production logs
- [ ] Add production security-header review

---

# 3. Users, roles & admin security

- [x] Users table
- [x] Administrator role
- [x] Editor role
- [x] Writer role
- [x] SEO Manager role
- [x] Active/inactive user flag
- [x] Session authentication
- [x] Password hashing
- [x] Login/logout
- [x] CSRF tokens
- [x] Role helpers
- [x] CLI first-admin creation command
- [ ] User CRUD
- [ ] Password change screen
- [ ] Password reset flow
- [ ] Login rate limiting
- [ ] Failed-login tracking
- [ ] Session rotation on login
- [ ] Session idle timeout
- [ ] Secure-cookie production settings
- [ ] Full permission matrix per role
- [ ] Admin activity/audit log
- [ ] Content Security Policy
- [ ] Security headers
- [ ] Rich-content XSS review

---

# 4. Categories, brands & products

## Categories
- [x] Category table
- [x] Parent-child relationship
- [x] Slug
- [x] Description
- [x] Image field
- [x] SEO title/meta description
- [x] Sort order
- [x] Active flag
- [x] Admin list/create/edit
- [x] Public `/category/{slug}` page
- [x] Category product grid
- [x] Category buying-guide section
- [x] Category article section
- [x] Category canonical metadata
- [ ] Nested breadcrumbs
- [ ] Archive/delete behavior
- [ ] Image upload/media picker
- [ ] Category pagination
- [ ] Category filters

## Brands
- [x] Brand table
- [x] Admin brand list
- [x] Create/edit brand
- [x] Brand website/logo URL fields
- [x] Product-brand relationship
- [ ] URL validation UX
- [ ] Brand logo upload
- [ ] Archive/delete flow
- [ ] Optional public brand pages

## Products
- [x] Central product table
- [x] Manual source
- [x] Amazon API source
- [x] Hybrid source
- [x] ASIN
- [x] Category relationship
- [x] Brand relationship
- [x] Product title/display title
- [x] Slug
- [x] Main image URL
- [x] Gallery storage field
- [x] Short/full description
- [x] Features
- [x] Pros/cons
- [x] Price/previous price/currency
- [x] Amazon URL
- [x] Affiliate URL
- [x] MediaPitch score
- [x] Best-for label
- [x] Editorial notes
- [x] Manual override metadata field
- [x] Last sync field
- [x] Active flag
- [x] Admin product list
- [x] Create/edit product
- [x] Product preview link
- [x] Public `/product/{slug}` page
- [ ] Slug auto-generation
- [ ] Friendly duplicate-slug validation
- [ ] Duplicate ASIN warning
- [ ] Product archive/delete flow
- [ ] Duplicate-product action
- [ ] Gallery editor
- [ ] Product media picker
- [ ] Related products
- [ ] Related buying guides
- [ ] Product change history
- [ ] Bulk actions
- [ ] CSV import/export

---

# 5. Flexible product specifications

- [x] Specification definitions table
- [x] Product specification values table
- [x] Category-specific definitions
- [x] Text type
- [x] Number type
- [x] Boolean type
- [x] Select type
- [x] Units
- [x] Select-option editor
- [x] Filterable flag
- [x] Comparable flag
- [x] Sort order
- [x] Admin definition CRUD
- [x] Dynamic product fields by category
- [x] Type validation on save
- [x] Product spec persistence
- [x] Public product specification table
- [x] Comparison pages consume comparable specs
- [ ] Dynamic public filtering by filterable specs
- [ ] Spec-definition archive/delete handling
- [ ] Better select-option UX

---

# 6. Storefront & navigation

- [x] MediaPitch-oriented visual direction
- [x] Existing MediaPitch logo direction reused
- [x] Responsive base layout
- [x] Global header/footer
- [x] Affiliate disclosure
- [x] Hero search
- [x] Featured categories
- [x] Featured products
- [x] Featured buying guides
- [x] Latest article section
- [x] Homepage categories link to real category pages
- [x] Homepage articles link to public blog pages
- [ ] Trending comparisons section
- [ ] Deals/featured-products section
- [ ] Homepage section controls in admin
- [ ] Mobile navigation polish
- [ ] Accessibility audit
- [ ] Keyboard navigation
- [ ] Skip link
- [ ] Improved focus states
- [ ] Better loading/empty states
- [ ] Final visual QA against MediaPitch brand

---

# 7. Buying guides

- [x] Buying-guide content type
- [x] Reusable product relationships
- [x] Rank per product
- [x] Guide-specific score
- [x] Guide-specific best-for label
- [x] Recommendation text
- [x] Custom CTA text
- [x] Admin list
- [x] Create/edit
- [x] Title/slug/category
- [x] Featured image URL
- [x] Intro/excerpt/body
- [x] SEO title/meta description
- [x] Draft/published/scheduled model
- [x] Publish date
- [x] Multiple products per guide
- [x] Transactional relationship save
- [x] Public guide route
- [x] Ranked product output
- [x] Affiliate rank/context tracking
- [x] Canonical/index metadata support
- [ ] Product autocomplete/search in guide editor
- [ ] Drag-and-drop ranking
- [ ] Duplicate-product guard UX
- [ ] Guide preview improvements
- [ ] FAQ editor/output
- [ ] “How we selected” section
- [ ] Table of contents
- [ ] Related guides/articles
- [ ] Related comparison selector
- [ ] Buying-guide schema markup
- [ ] Breadcrumbs

---

# 8. Blog / editorial CMS

- [x] Blog content type
- [x] Admin article list
- [x] Article create/edit
- [x] Category assignment
- [x] Featured image URL
- [x] Excerpt/body
- [x] Draft/scheduled/published states
- [x] Publish date
- [x] Scheduled public visibility
- [x] SEO title/meta description
- [x] Canonical URL field/output
- [x] Index/noindex field/output
- [x] Public `/blog` index
- [x] Public `/blog/{slug}` route
- [x] Author/category/date display
- [ ] Safe Markdown or rich-text editor
- [ ] Tags model
- [ ] Tags UI
- [ ] Product embeds
- [ ] Related articles
- [ ] Media picker
- [ ] Dedicated blog category pages if needed
- [ ] Author pages if useful
- [ ] Article schema
- [ ] Article-specific OG image

---

# 9. Comparisons & reviews

## Comparisons
- [x] Comparison content type
- [x] Admin comparison list
- [x] Comparison create/edit
- [x] Select two or more products
- [x] Duplicate product prevention in save layer
- [x] Product ordering
- [x] Editorial verdict/body
- [x] Draft/scheduled/published states
- [x] SEO title/meta/canonical/index fields
- [x] Public `/compare/{slug}` route
- [x] Dynamic side-by-side table
- [x] Category-aware comparable specs
- [x] Brand/score/best-for/price rows
- [x] Affiliate CTAs with comparison context
- [ ] Comparison index page
- [ ] Related comparisons
- [ ] Better responsive comparison table UX
- [ ] Comparison structured data if appropriate

## Reviews
- [x] Review content type exists
- [ ] Admin review list/editor
- [ ] Review-product relationship
- [ ] Rating/score UI
- [ ] Public review route
- [ ] Review structured data

---

# 10. Search, browsing & filters

- [x] Search route
- [x] Product text search
- [x] Product brand/category matching
- [x] Search categories
- [x] Search buying guides
- [x] Search comparisons
- [x] Search blog articles
- [x] Grouped search results UI
- [ ] Search reviews once review pages exist
- [ ] Pagination
- [ ] Sorting
- [ ] Brand filter
- [ ] Price filter
- [ ] Score filter
- [ ] Category filter
- [ ] Dynamic spec filters
- [ ] Search analytics
- [ ] Autocomplete/suggestions

---

# 11. Affiliate links & analytics

- [x] Central affiliate URL on products
- [x] `/go/{product}` redirect
- [x] Click table
- [x] Product tracking
- [x] Source content tracking
- [x] Rank tracking
- [x] CTA location tracking
- [x] Referrer tracking
- [x] User-agent tracking
- [x] Campaign tracking
- [x] Dashboard total clicks
- [x] Dashboard most-clicked products
- [x] Comparison CTA context tracking
- [ ] Verify link handling against current Amazon rules
- [ ] Bot/internal-click filtering
- [ ] Date filters
- [ ] Guide analytics
- [ ] Comparison analytics
- [ ] CTA analytics
- [ ] Rank analytics
- [ ] Campaign reports
- [ ] CSV export
- [ ] Privacy/data-retention review

---

# 12. Admin dashboard

- [x] Admin layout
- [x] Responsive styling
- [x] Product count
- [x] Manual product count
- [x] API product count
- [x] Published guide count
- [x] Draft count
- [x] Affiliate click count
- [x] Most-clicked products
- [ ] Hybrid product count
- [ ] Products requiring review
- [ ] Amazon API status widget
- [ ] Last successful Amazon sync
- [ ] Recent content activity
- [ ] Click graph
- [ ] Guide/comparison performance widgets
- [ ] Quick-create actions

---

# 13. Media library

- [ ] Media table
- [ ] Upload endpoint
- [ ] MIME validation
- [ ] Image size limits
- [ ] Image optimization
- [ ] Thumbnail generation
- [ ] Media browser
- [ ] Media search
- [ ] Product picker
- [ ] Category picker
- [ ] Guide/blog picker
- [ ] Alt text
- [ ] Safe delete
- [ ] Future S3/R2 storage abstraction

---

# 14. SEO

- [x] SEO title fields
- [x] Meta-description fields
- [x] Slug-based product routes
- [x] Slug-based guide routes
- [x] Slug-based comparison routes
- [x] Slug-based blog routes
- [x] Category canonical support
- [x] Product canonical support
- [x] Guide canonical/index support
- [x] Blog canonical/index support
- [x] Comparison canonical/index support
- [x] Base Open Graph title/description output
- [ ] Dynamic OG images
- [ ] Twitter card metadata
- [ ] XML sitemap index
- [ ] Product sitemap
- [ ] Content sitemap
- [ ] Category sitemap
- [ ] `robots.txt`
- [ ] Breadcrumb UI + schema
- [ ] Product structured data
- [ ] Article structured data
- [ ] Review structured data
- [ ] Buying-guide structured data
- [ ] Image alt-text workflow
- [ ] Redirect admin UI
- [ ] Auto-redirect when published slug changes
- [ ] SEO preview in editors
- [ ] Internal-link helper

---

# 15. Amazon Creators API

## Architecture & compliance
- [x] Amazon provider boundary
- [x] Manual workflow independent from API
- [x] Product source abstraction
- [x] API source/sync fields in DB
- [ ] Verify current Creators API documentation
- [ ] Verify current Associates policy
- [ ] Verify image usage rules
- [ ] Verify price/freshness rules
- [ ] Verify permitted caching/storage
- [ ] Verify disclosure/branding requirements

## Settings
- [ ] Amazon settings admin page
- [ ] Marketplace
- [ ] Associate/partner tag
- [ ] Credential ID
- [ ] Secret
- [ ] Credential version
- [ ] Enabled toggle
- [ ] Test connection
- [ ] Last success/error
- [ ] Encrypt stored secrets
- [ ] Prevent credentials in logs/frontend

## Import
- [ ] Products → Import from Amazon
- [ ] Search request
- [ ] Search results UI
- [ ] Import selected product
- [ ] Map currently permitted fields
- [ ] Store affiliate-attributed URL where supported
- [ ] Store marketplace
- [ ] Store sync timestamp
- [ ] Preserve editorial fields
- [ ] Gracefully handle missing fields

## Synchronization
- [ ] Refresh imported data
- [ ] Field-level override strategy
- [ ] Never overwrite protected editorial fields
- [ ] API health monitoring
- [ ] Last sync/error display
- [ ] Retry/manual refresh
- [ ] Site remains fully usable during API outage

---

# 16. Website settings & integrations

- [ ] Website settings page
- [ ] Site name/tagline
- [ ] Homepage controls
- [ ] Global affiliate disclosure
- [ ] Default SEO metadata
- [ ] Social metadata defaults
- [ ] Integration settings structure
- [ ] Restrict credential settings to administrators

---

# 17. Testing & quality

- [x] PHP syntax checks in GitHub Actions
- [ ] Automated DB integration tests
- [ ] Repository/unit test structure
- [ ] Homepage smoke test
- [ ] Search smoke tests
- [ ] Category route tests
- [ ] Product route tests
- [ ] Guide route tests
- [ ] Blog route tests
- [ ] Comparison route tests
- [ ] Affiliate redirect tests
- [ ] Authentication tests
- [ ] CSRF tests
- [ ] Role permission tests
- [ ] Product CRUD tests
- [ ] Specification validation tests
- [ ] Buying-guide CRUD tests
- [ ] Comparison CRUD tests
- [ ] Browser/mobile QA
- [ ] Accessibility QA
- [ ] Security review

---

# 18. Performance

- [x] Public frontend reads local DB rather than calling Amazon per page
- [x] Public homepage survives DB outage visibly
- [ ] DB indexes/query review
- [ ] Pagination for large datasets
- [ ] Cache common homepage/category queries
- [ ] Image lazy-loading review
- [ ] Asset minification/build strategy if needed
- [ ] Response compression verification
- [ ] Production caching headers
- [ ] Core Web Vitals pass

---

# 19. Phase 1 launch checklist

The first launch is complete when a non-technical editor can create a full ranked buying guide manually and the public site is stable without Amazon API credentials.

- [x] Product/category CMS foundation
- [x] Brand management
- [x] Flexible specifications
- [x] Buying-guide CMS
- [x] Blog CMS
- [x] Comparison CMS
- [x] Public homepage
- [x] Product pages
- [x] Category pages
- [x] Guide pages
- [x] Blog pages
- [x] Comparison pages
- [x] Search across major content types
- [x] Affiliate redirect/click tracking
- [x] Core SEO metadata fields/output
- [ ] Production DB configured
- [ ] Production admin created
- [ ] Manual end-to-end “Best 10 ACs Under ₹40,000” test
- [ ] Production smoke test
- [ ] Sitemap/robots
- [ ] Basic media uploads
- [ ] Security hardening
- [ ] Mobile/accessibility QA

---

# Immediate next queue

1. [ ] Add XML sitemap + `robots.txt`
2. [ ] Add basic media upload/library
3. [ ] Add product/category filters using filterable specifications
4. [ ] Improve guide editor with product search + duplicate guard
5. [ ] Add review CMS/public pages
6. [ ] Add admin user management
7. [ ] Add dashboard quick-create + hybrid/API status widgets
8. [ ] Add schema markup and breadcrumbs
9. [!] Configure and verify production MariaDB/admin account
10. [ ] Verify current Amazon Creators API and Associates rules before implementing real API calls
