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
- Known deployment issue: production has returned 403; root `index.php` + root `.htaccess` compatibility bridge has been added, but hosting/runtime still needs verification

---

# 1. Foundation & deployment

- [x] PHP application structure
- [x] MariaDB schema
- [x] `.env.example`
- [x] `.env` ignored
- [x] PDO database layer
- [x] Project autoloader
- [x] View renderer
- [x] Public front controller
- [x] Apache rewrite rules under `public/`
- [x] Root-level deployment compatibility `index.php`
- [x] Root-level deployment compatibility `.htaccess`
- [x] Root rules block direct access to `src/`, `database/`, `views/`, `.env`, README and task files
- [x] `/health` endpoint
- [x] 404 page
- [x] 500 page
- [x] README setup instructions
- [x] GitHub Actions PHP syntax workflow added
- [!] Confirm why production returns/returned 403
- [ ] Confirm production PHP version
- [ ] Confirm PDO MySQL extension
- [ ] Confirm production document root
- [ ] Confirm Apache/Nginx/control-panel configuration
- [ ] Configure production `.env`
- [ ] Create/import production MariaDB schema
- [ ] Create first production admin account
- [ ] Verify homepage on production
- [ ] Verify `/health` on production
- [ ] Verify `/admin/login` on production
- [ ] Add deployment documentation
- [ ] Add rollback procedure
- [ ] Add database backup procedure
- [ ] Add smoke-test checklist
- [ ] Add `.editorconfig`
- [ ] Add coding standards
- [ ] Add database migration/versioning strategy
- [ ] Add seed-data strategy

---

# 2. Authentication, roles & security

- [x] Users table
- [x] Administrator role
- [x] Editor role
- [x] Writer role
- [x] SEO manager role
- [x] Active/inactive user flag
- [x] Session login
- [x] Logout
- [x] Password hashing
- [x] CSRF protection for admin writes
- [x] Basic role helpers
- [x] CLI admin creation command
- [ ] Full permission matrix enforcement
- [ ] User CRUD
- [ ] Password change screen
- [ ] Password reset flow
- [ ] Session ID rotation on login
- [ ] Secure production cookie settings
- [ ] Session idle timeout
- [ ] Login rate limiting
- [ ] Failed-login tracking
- [ ] Admin audit/activity log
- [ ] Security headers / CSP
- [ ] Rich-content XSS sanitization review

---

# 3. Categories & brands

## Categories
- [x] Categories table
- [x] Parent/child hierarchy
- [x] Slugs
- [x] Description
- [x] Image URL field
- [x] SEO title/meta description
- [x] Sort order
- [x] Active/inactive flag
- [x] Admin category list/create/edit
- [x] Public category route
- [x] Public category description/content
- [x] Category product grid
- [x] Category buying guides
- [x] Category blog posts
- [x] Category SEO title/meta output
- [x] Category canonical URL
- [ ] Archive/delete behavior
- [ ] Image upload/media picker
- [ ] Breadcrumbs
- [ ] Category filters
- [ ] Pagination

## Brands
- [x] Brands table
- [x] Admin brand list
- [x] Brand create/edit
- [x] Website URL field
- [x] Logo URL field
- [ ] URL validation polish
- [ ] Brand archive/delete
- [ ] Logo upload/media picker
- [ ] Public brand pages if useful

---

# 4. Product catalogue

- [x] Central products table
- [x] Manual product source
- [x] Amazon API product source
- [x] Hybrid API + editorial source
- [x] ASIN
- [x] Internal title + editorial display title
- [x] Slug
- [x] Category relationship
- [x] Brand relationship
- [x] Main image URL
- [x] Gallery data field
- [x] Short/full descriptions
- [x] Features
- [x] Pros/cons
- [x] Price / previous price / currency
- [x] Amazon URL
- [x] Affiliate URL
- [x] Editorial score
- [x] Best-for label
- [x] Editorial notes
- [x] Manual override metadata field
- [x] Last-sync field
- [x] Active/inactive flag
- [x] Admin product list
- [x] Product create/edit
- [x] Source selector
- [x] Product preview link from admin
- [ ] Slug auto-generation
- [ ] Friendly duplicate slug validation
- [ ] Duplicate ASIN warning
- [ ] Product archive/delete
- [ ] Duplicate product action
- [ ] Image upload/media picker
- [ ] Gallery editor
- [ ] Product change history
- [ ] Bulk actions
- [ ] CSV import/export

---

# 5. Flexible specifications

- [x] Specification-definition table
- [x] Product-specification values table
- [x] Category-specific definitions
- [x] Text type
- [x] Number type
- [x] Boolean type
- [x] Select type
- [x] Unit field
- [x] Filterable flag
- [x] Comparable flag
- [x] Sort order
- [x] Admin specification definition list/create/edit
- [x] Select-option editor
- [x] Dynamic product fields based on selected category
- [x] Backend validation by data type
- [x] Product specification persistence
- [x] Public product specification table
- [ ] Delete/archive specification definitions safely
- [ ] Comparison specification matrix
- [ ] Public filters from filterable specs

---

# 6. Public storefront

## Global
- [x] MediaPitch visual direction
- [x] Existing MediaPitch logo reuse
- [x] Responsive base layout
- [x] Header
- [x] Footer
- [x] Search box
- [x] Affiliate disclosure
- [ ] Mobile navigation polish
- [ ] Accessibility review
- [ ] Keyboard navigation
- [ ] Skip links
- [ ] Focus-state review
- [ ] Empty/loading states
- [ ] Final brand/design QA

## Homepage
- [x] Hero/search
- [x] Featured categories
- [x] Category cards link to real category pages
- [x] Recent/top products section
- [x] Buying-guide section
- [x] Latest articles section
- [x] Latest articles link to public posts/blog index
- [ ] Trending comparisons
- [ ] Deals/featured-products block
- [ ] Admin homepage ordering
- [ ] Enable/disable homepage sections

## Product page
- [x] Product route
- [x] Product title/image
- [x] Brand/category
- [x] Price
- [x] Score
- [x] Best-for badge
- [x] Features/pros/cons
- [x] Flexible specification table
- [x] Affiliate CTA
- [x] Canonical URL supplied
- [ ] Product gallery
- [ ] Related products
- [ ] Related guides
- [ ] Product structured data
- [ ] Product-specific Open Graph metadata

---

# 7. Buying guides

- [x] Buying-guide content model
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
- [x] Canonical/index metadata support on guide route
- [ ] Product autocomplete/search in guide editor
- [ ] Drag-and-drop ranking
- [ ] Duplicate product guard UX
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
- [x] Excerpt
- [x] Article body
- [x] Draft state
- [x] Scheduled state
- [x] Published state
- [x] Publish date
- [x] Scheduled posts become publicly visible after publish time
- [x] SEO title
- [x] Meta description
- [x] Canonical URL field/output
- [x] Index/noindex field/output
- [x] Public `/blog` index
- [x] Public `/blog/{slug}` article route
- [x] Author/category/date display
- [ ] Safe rich-text or Markdown editor
- [ ] Tags model
- [ ] Tags admin UI
- [ ] Product embeds
- [ ] Related articles
- [ ] Media picker
- [ ] Blog category pages beyond category landing integration
- [ ] Author pages if useful
- [ ] Article structured data
- [ ] Article-specific Open Graph image

---

# 9. Reviews & comparisons

## Reviews
- [x] Review type exists in content schema
- [ ] Admin review list/editor
- [ ] Product relationship
- [ ] Rating/score UI
- [ ] Public review route
- [ ] Review structured data

## Comparisons
- [x] Comparison type exists in schema
- [x] Comparable specification flag exists
- [ ] Comparison admin list/editor
- [ ] Select two or more products
- [ ] Editorial verdict
- [ ] Dynamic spec comparison table
- [ ] Category-aware comparable attributes
- [ ] Public comparison route
- [ ] Comparison SEO metadata
- [ ] Related comparisons

---

# 10. Search, categories & filtering

- [x] Product search route
- [x] Product text search
- [x] Search results page
- [x] Public category landing pages
- [x] Category product grids
- [x] Category guide/article sections
- [ ] Search articles/content
- [ ] Search categories
- [ ] Pagination
- [ ] Sorting
- [ ] Brand filter
- [ ] Price filter
- [ ] Score filter
- [ ] Category filter
- [ ] Dynamic spec filters
- [ ] Search analytics
- [ ] Autocomplete

---

# 11. Affiliate links & analytics

- [x] Central affiliate URL on products
- [x] `/go/{product}` redirect route
- [x] Affiliate click table
- [x] Track product
- [x] Track source content
- [x] Track rank
- [x] Track CTA location
- [x] Track referrer
- [x] Track user agent
- [x] Track campaign
- [x] Dashboard total clicks
- [x] Dashboard most-clicked products
- [ ] Verify redirect/link handling against current Amazon rules
- [ ] Bot/internal-click filtering
- [ ] Date filters
- [ ] Guide analytics
- [ ] CTA analytics
- [ ] Rank analytics
- [ ] Campaign reports
- [ ] CSV export
- [ ] Data-retention/privacy review

---

# 12. Admin dashboard

- [x] Dashboard layout
- [x] Product count
- [x] Manual product count
- [x] API product count
- [x] Published guide count
- [x] Draft count
- [x] Affiliate click count
- [x] Top-clicked products
- [ ] Hybrid product count
- [ ] Products requiring review
- [ ] Amazon status
- [ ] Last Amazon sync
- [ ] Recent content activity
- [ ] Click graph
- [ ] Guide performance
- [ ] Quick-create actions

---

# 13. Media library

- [ ] Media table
- [ ] Upload endpoint
- [ ] MIME validation
- [ ] File-size limits
- [ ] Image optimization
- [ ] Thumbnail generation
- [ ] Media browser/search
- [ ] Alt text
- [ ] Product picker
- [ ] Category picker
- [ ] Guide/blog picker
- [ ] Safe delete
- [ ] Storage abstraction for S3/R2 later

---

# 14. SEO

- [x] SEO title fields
- [x] Meta-description fields
- [x] Slug routes
- [x] Redirect table
- [x] Shared canonical output support
- [x] Shared robots index/noindex output support
- [x] Basic Open Graph title/description
- [x] Canonical supplied on product/category/blog/guide routes
- [ ] Open Graph images/types per content type
- [ ] Twitter metadata
- [ ] XML sitemap index
- [ ] Product sitemap
- [ ] Content sitemap
- [ ] Category sitemap
- [ ] robots.txt management
- [ ] Breadcrumb schema
- [ ] Product schema
- [ ] Article schema
- [ ] Review schema
- [ ] Redirect admin UI
- [ ] Automatic redirect on slug change
- [ ] SEO preview in admin

---

# 15. Amazon Creators API

## Architecture & policy
- [x] Amazon provider boundary exists
- [x] Public site does not depend on Amazon API
- [x] Manual product workflow remains first-class
- [x] Source/sync fields exist
- [ ] Verify current Creators API docs
- [ ] Verify current Associates policies

## Settings
- [ ] Settings → Amazon page
- [ ] Marketplace
- [ ] Associate/partner tag
- [ ] Credential ID
- [ ] Credential secret
- [ ] Credential version
- [ ] API enable/disable
- [ ] Test connection
- [ ] Last success/error display
- [ ] Encrypt secrets at rest
- [ ] Ensure secrets never leak to frontend/logs

## Search/import/sync
- [ ] Amazon product search UI
- [ ] Search request adapter
- [ ] Import selected product
- [ ] Map only documented API fields
- [ ] Affiliate-attributed URL support where API provides it
- [ ] Store marketplace
- [ ] Store sync time
- [ ] Preserve editorial overrides
- [ ] Refresh imported product data
- [ ] Handle API failure gracefully
- [ ] API health monitoring

---

# 16. Settings & administration

- [ ] Website settings page
- [ ] Site title/tagline
- [ ] Global affiliate disclosure
- [ ] Default SEO values
- [ ] Integrations page
- [ ] Redirect manager
- [ ] User manager
- [ ] Role/permission manager

---

# 17. Testing & quality

- [x] PHP syntax workflow definition exists
- [ ] Confirm GitHub Actions is actually running
- [ ] Unit-test setup
- [ ] Database integration tests
- [ ] Auth tests
- [ ] Product CRUD tests
- [ ] Specification validation tests
- [ ] Guide CRUD tests
- [ ] Blog CRUD tests
- [ ] Affiliate redirect tests
- [ ] SEO output tests
- [ ] Amazon adapter tests/mocks
- [ ] Mobile browser QA
- [ ] Accessibility audit
- [ ] Lighthouse/PageSpeed review

---

# 18. Phase 1 launch definition

Phase 1 is launch-ready when a non-technical editor can manually create categories, products and a ranked buying guide without Amazon API access.

- [x] Product/category database foundation
- [x] Manual product editor
- [x] Brand editor
- [x] Flexible category specifications
- [x] Buying-guide editor
- [x] Blog editor
- [x] Public homepage foundation
- [x] Public category pages
- [x] Public product pages
- [x] Public guide pages
- [x] Public blog pages
- [x] Affiliate redirect/click tracking
- [ ] Production database configured
- [ ] Production admin created
- [ ] Production landing page verified
- [ ] Production admin verified
- [ ] Media uploads
- [ ] Core SEO/sitemaps
- [ ] Basic security hardening
- [ ] Smoke/integration testing
- [ ] Amazon compliance review for manual affiliate links

---

# Immediate next work queue

1. [~] Get production landing page confirmed working and configure database/admin when hosting details are available.
2. [ ] Add comparison editor + dynamic specification comparison table.
3. [ ] Build media upload/library so editors stop pasting image URLs.
4. [ ] Add sitemap/robots/structured-data SEO layer.
5. [ ] Improve slug validation/generation and duplicate handling.
6. [ ] Build Settings → Amazon and verify current Creators API documentation/policies before API calls are implemented.
7. [ ] Add integration tests and ensure GitHub Actions actually executes.
