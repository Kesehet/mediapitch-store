# MediaPitch Store — Project Task Tracker

This file is the project source of truth for implementation progress.

## Status legend

- [x] Done
- [ ] Not started / remaining
- [~] In progress
- [!] Blocked / needs external input or production access

## Current snapshot

**Production branch:** `main`

**Active development branches:**
- `agent/admin-cms` — initial admin CMS implementation
- `agent/project-tracker` — task tracker based on the admin CMS branch

**Current draft PR:** #1 — Build initial admin CMS

**Current production URL:** `https://store.mediapitch.in/`

**Known production issue:** 403 response. The application currently expects the web-server document root to point to `public/`. This needs deployment/hosting verification before treating it as an application bug.

---

# 1. Project foundation

- [x] Create `Kesehet/mediapitch-store` repository
- [x] Define PHP + MariaDB architecture
- [x] Keep application independent of Composer initially
- [x] Add `.gitignore`
- [x] Ignore `.env`
- [x] Add `.env.example`
- [x] Add MariaDB configuration placeholders
- [x] Add application bootstrap
- [x] Add environment loader
- [x] Add PSR-style project autoloader
- [x] Add PDO database connection layer
- [x] Add reusable view renderer
- [x] Add public front controller
- [x] Add Apache rewrite rules
- [x] Add `/health` endpoint
- [x] Add 404 page
- [x] Add 500 page
- [x] Add basic README/setup instructions
- [x] Add GitHub Actions PHP syntax-check workflow
- [ ] Add production deployment documentation
- [ ] Add development environment documentation
- [ ] Add database migration/versioning strategy
- [ ] Add seed-data strategy
- [ ] Add application version/release convention
- [ ] Add `.editorconfig`
- [ ] Add coding-standard documentation

---

# 2. Production / deployment

- [!] Verify why `https://store.mediapitch.in/` currently returns 403
- [ ] Confirm production web server type: Apache / Nginx / hosting control panel
- [ ] Confirm production document root
- [ ] Point production document root to repository `public/` directory
- [ ] Confirm `public/index.php` is executable by PHP runtime
- [ ] Confirm `.htaccess` overrides are allowed if Apache is used
- [ ] Confirm rewrite module is enabled if Apache is used
- [ ] Confirm PHP version on production
- [ ] Confirm PDO MySQL extension on production
- [ ] Configure production `.env`
- [ ] Create production MariaDB database
- [ ] Import database schema safely
- [ ] Create first production administrator account
- [ ] Verify `/health` in production
- [ ] Verify homepage in production
- [ ] Verify admin login in production
- [ ] Verify public affiliate redirect route in production
- [ ] Add deployment rollback procedure
- [ ] Add database backup procedure
- [ ] Add deployment smoke-test checklist
- [ ] Add production log location/documentation
- [ ] Add HTTPS/security-header review

---

# 3. Database architecture

## Users and permissions

- [x] Users table
- [x] Role field
- [x] Administrator role
- [x] Editor role
- [x] Writer role
- [x] SEO manager role
- [x] Active/inactive user state
- [ ] User management CRUD
- [ ] Password reset/change workflow
- [ ] Last-login tracking
- [ ] Failed-login tracking/rate limiting
- [ ] Permission matrix enforcement per role

## Categories

- [x] Categories table
- [x] Parent/child category relationship
- [x] Slug
- [x] Description
- [x] Category image field
- [x] SEO title
- [x] Meta description
- [x] Sort order
- [x] Active/inactive state
- [x] Admin category list
- [x] Admin category create/edit
- [ ] Category deletion/archive behavior
- [ ] Category image upload rather than URL-only field
- [ ] Category frontend pages
- [ ] Nested category breadcrumbs
- [ ] Category-specific filter UI

## Brands

- [x] Brands database table
- [ ] Brand admin list
- [ ] Brand create/edit form
- [ ] Brand logo upload
- [ ] Brand website URL validation
- [ ] Brand archive/delete flow
- [ ] Brand frontend pages if useful

## Products

- [x] Central products table
- [x] Product source: manual
- [x] Product source: Amazon API
- [x] Product source: hybrid
- [x] ASIN field
- [x] Product title
- [x] Editorial display title
- [x] Slug
- [x] Category relationship
- [x] Brand relationship
- [x] Main image URL
- [x] Gallery JSON field
- [x] Short description
- [x] Full description
- [x] Features
- [x] Pros
- [x] Cons
- [x] Price
- [x] Previous price
- [x] Currency
- [x] Amazon URL
- [x] Affiliate URL
- [x] Custom score
- [x] Best-for label
- [x] Editorial notes
- [x] Manual override metadata field
- [x] Last synced field
- [x] Active/inactive state
- [x] Product list in admin
- [x] Product create/edit form
- [x] Manual/API/hybrid source selector in admin
- [ ] Product delete/archive workflow
- [ ] Product duplicate action
- [ ] Slug generation helper
- [ ] Duplicate slug validation with user-friendly errors
- [ ] Duplicate ASIN warning
- [ ] Product image upload/media-picker integration
- [ ] Gallery editor UI
- [ ] Product preview from admin
- [ ] Product change history/audit trail
- [ ] Product bulk actions
- [ ] Product CSV import/export

---

# 4. Flexible product specifications

- [x] Specification definitions table
- [x] Product specification values table
- [x] Category-to-specification relationship
- [x] Text specification type
- [x] Number specification type
- [x] Boolean specification type
- [x] Select specification type
- [x] Filterable flag
- [x] Comparable flag
- [x] Sort order
- [ ] Admin specification-definition CRUD
- [ ] Category-specific specification editor
- [ ] Dynamic product specification fields based on category
- [ ] Specification validation by data type
- [ ] Select-option editor
- [ ] Units support in UI
- [ ] Product frontend specification table
- [ ] Comparison frontend specification matrix
- [ ] Public filtering based on filterable specs

---

# 5. Public storefront / discovery frontend

## Global layout

- [x] MediaPitch-oriented visual direction
- [x] Reuse existing MediaPitch logo/brand direction
- [x] Responsive base styling
- [x] Shopping/discovery-style layout foundation
- [x] Global header
- [x] Global footer
- [x] Affiliate disclosure area
- [ ] Final design QA against existing MediaPitch branding
- [ ] Mobile navigation polish
- [ ] Accessibility review
- [ ] Keyboard navigation review
- [ ] Skip links
- [ ] Improved focus states
- [ ] Loading/empty states

## Homepage

- [x] Hero/search concept
- [x] Featured categories
- [x] Featured/recent products
- [x] Buying-guide section
- [x] Latest article section
- [ ] Trending comparisons section
- [ ] Deals/featured-products section
- [ ] Admin controls for homepage ordering
- [ ] Homepage section enable/disable settings

## Product page

- [x] Product route
- [x] Product title
- [x] Main image
- [x] Price display where stored
- [x] Editorial score
- [x] Best-for label
- [x] Features/pros/cons rendering
- [x] Affiliate CTA
- [ ] Product gallery
- [ ] Flexible specification rendering
- [ ] Related buying guides
- [ ] Related products
- [ ] Structured data
- [ ] Canonical metadata
- [ ] Open Graph metadata

## Search

- [x] Search route
- [x] Product search query
- [x] Search results page
- [ ] Search categories
- [ ] Search content/articles
- [ ] Search filters
- [ ] Search pagination
- [ ] Search sorting
- [ ] Search analytics
- [ ] Autocomplete/suggestions

## Category pages

- [ ] Public category landing route
- [ ] Category description/content
- [ ] Category product grid
- [ ] Category buying guides
- [ ] Category blog articles
- [ ] Category filters
- [ ] Category SEO metadata
- [ ] Category breadcrumbs
- [ ] Pagination

---

# 6. Affiliate link handling and analytics

- [x] Central affiliate URL field on products
- [x] Central `/go/{product}` outbound route
- [x] Redirect to stored affiliate URL
- [x] Affiliate click table
- [x] Store product per click
- [x] Store content/page context when supplied
- [x] Store rank when supplied
- [x] Store CTA location when supplied
- [x] Store referrer
- [x] Store user agent
- [x] Store campaign parameter
- [x] Dashboard total outbound-click count
- [x] Dashboard most-clicked products
- [ ] Validate redirect implementation against current Amazon Associates rules
- [ ] Add bot/internal-click filtering strategy
- [ ] Add click analytics date filters
- [ ] Add click analytics by guide
- [ ] Add click analytics by CTA location
- [ ] Add click analytics by rank
- [ ] Add click analytics export
- [ ] Add campaign reporting
- [ ] Add privacy/data-retention review

---

# 7. Authentication and admin security

- [x] Session-based authentication
- [x] Password hashing
- [x] Login page
- [x] Logout
- [x] Authentication guard
- [x] Role helper
- [x] CSRF token generation
- [x] CSRF validation for admin writes
- [x] CLI administrator creation command
- [ ] Login rate limiting
- [ ] Secure cookie settings for production
- [ ] Session rotation on login
- [ ] Session idle timeout
- [ ] Force HTTPS in production
- [ ] User management
- [ ] Password change screen
- [ ] Admin activity/audit log
- [ ] Restrict credential settings to administrators only
- [ ] Security review for XSS in rich content
- [ ] Content Security Policy
- [ ] Security headers

---

# 8. Admin dashboard

- [x] Admin layout
- [x] Responsive admin styling
- [x] Product count
- [x] Manual product count
- [x] API product count
- [x] Published guide count
- [x] Draft content count
- [x] Affiliate-click count
- [x] Most-clicked products
- [ ] Hybrid product count
- [ ] Products requiring review
- [ ] Amazon API status widget
- [ ] Last successful Amazon sync
- [ ] Recent content activity
- [ ] Recent outbound click graph
- [ ] Guide performance widget
- [ ] Quick-create actions

---

# 9. Buying guides

## Data model

- [x] Buying guide content type
- [x] Reusable content-to-product pivot
- [x] Article-specific product rank
- [x] Article-specific score
- [x] Article-specific best-for label
- [x] Article-specific recommendation
- [x] Article-specific CTA text
- [x] Sort-order field

## Admin workflow

- [x] Buying-guide list
- [x] Buying-guide create/edit
- [x] Title
- [x] Slug
- [x] Category
- [x] Featured image URL
- [x] Intro/excerpt
- [x] Main body
- [x] SEO title
- [x] Meta description
- [x] Draft/published/scheduled status model
- [x] Publish date
- [x] Add multiple existing products
- [x] Product rank per guide
- [x] Score per guide
- [x] Best-for label per guide
- [x] Recommendation per guide
- [x] Custom CTA per guide
- [x] Save guide/product relationships transactionally
- [ ] Drag-and-drop ranking UI
- [ ] Product search/autocomplete inside guide editor
- [ ] Prevent accidental duplicate product selection gracefully
- [ ] Guide preview before publishing
- [ ] Draft autosave
- [ ] FAQ editor
- [ ] "How we selected" structured section
- [ ] Related comparisons selector
- [ ] Related articles selector
- [ ] Per-guide affiliate disclosure controls if required

## Frontend

- [x] Public buying-guide route
- [x] Ranked product sections
- [x] Product CTA context/rank tracking
- [ ] Cleaner editorial section formatting
- [ ] FAQ output
- [ ] Table of contents
- [ ] Sticky/mobile CTA where appropriate
- [ ] Product comparison summary table
- [ ] Related guides/articles
- [ ] Buying-guide structured data
- [ ] Breadcrumbs

---

# 10. Blog / editorial CMS

- [x] Generic content table supports blog type
- [x] Generic content table supports review type
- [x] Generic content table supports comparison type
- [x] Draft/published/scheduled states in schema
- [x] SEO fields in schema
- [ ] Blog admin list
- [ ] Blog create/edit screen
- [ ] Rich-text or safe Markdown editor
- [ ] Categories in blog editor
- [ ] Tags model
- [ ] Tags editor
- [ ] Featured image/media picker
- [ ] Product embeds in blog posts
- [ ] Related articles selector
- [ ] Scheduled publishing behavior
- [ ] Article frontend route
- [ ] Blog index
- [ ] Blog category pages
- [ ] Blog author pages if desired
- [ ] Article structured data
- [ ] Article social metadata

---

# 11. Reviews

- [x] Review content type exists in schema
- [ ] Review admin list
- [ ] Review editor
- [ ] Review-to-product relationship
- [ ] Review rating/score UI
- [ ] Review frontend route
- [ ] Review structured data where appropriate
- [ ] Related reviews/products

---

# 12. Comparisons

- [x] Comparison content type exists in schema
- [x] Flexible specs architecture supports comparison concept
- [ ] Comparison admin list
- [ ] Comparison editor
- [ ] Select two or more products
- [ ] Comparison-specific editorial verdict
- [ ] Dynamic comparison table
- [ ] Category-aware comparison attributes
- [ ] Comparison frontend route
- [ ] Related comparisons
- [ ] Comparison SEO metadata

---

# 13. Media library

- [ ] Media database table
- [ ] Upload endpoint
- [ ] Secure MIME/type validation
- [ ] Image size limits
- [ ] Image optimization
- [ ] Thumbnail generation
- [ ] Media browser
- [ ] Media search
- [ ] Media picker for products
- [ ] Media picker for categories
- [ ] Media picker for blog/guides
- [ ] Alt-text management
- [ ] Delete unused media safely
- [ ] Storage abstraction for future S3/R2 support

---

# 14. SEO

- [x] SEO title fields in core models
- [x] Meta-description fields in core models
- [x] Slug-based product routes
- [x] Slug-based guide routes
- [x] Redirect table in database
- [ ] Canonical output on all applicable pages
- [ ] Open Graph metadata
- [ ] Twitter metadata
- [ ] XML sitemap index
- [ ] Product sitemap
- [ ] Content sitemap
- [ ] Category sitemap
- [ ] robots.txt management
- [ ] Breadcrumb structured data
- [ ] Product structured data where appropriate
- [ ] Article structured data
- [ ] Review structured data where appropriate
- [ ] Image alt-text workflow
- [ ] Index/noindex controls in admin UI
- [ ] Redirect admin UI
- [ ] Automatic redirect when a published slug changes
- [ ] Internal-link helper/tools
- [ ] SEO preview in editors

---

# 15. Amazon Creators API

## Architecture

- [x] Amazon integration boundary/provider interface
- [x] Product source abstraction avoids public dependency on Amazon
- [x] Manual product workflow works independently of API conceptually
- [x] Database fields for API marketplace/source/sync state
- [ ] Verify current Amazon Creators API documentation before implementation
- [ ] Verify current Amazon Associates policy requirements

## Settings

- [ ] Settings → Amazon admin screen
- [ ] Marketplace setting
- [ ] Associate/partner tag
- [ ] Creators API credential ID
- [ ] Creators API secret
- [ ] Credential version
- [ ] API enabled/disabled toggle
- [ ] Test connection button
- [ ] Last successful connection
- [ ] Last error
- [ ] Secure encryption for stored secrets
- [ ] Ensure secrets never appear in frontend responses/logs

## Search/import

- [ ] Products → Import from Amazon screen
- [ ] Product search request
- [ ] Search-results UI
- [ ] Select/import Amazon product
- [ ] Map supported Amazon fields into internal product
- [ ] Generate/store permitted affiliate-attributed URL
- [ ] Store API marketplace
- [ ] Store last sync timestamp
- [ ] Preserve editorial/manual fields
- [ ] Handle missing API fields gracefully

## Synchronization

- [ ] Refresh imported product data
- [ ] Field-level manual override strategy
- [ ] Prevent editor fields being overwritten
- [ ] API failure state
- [ ] Retry action
- [ ] Sync logs
- [ ] Background/cron sync command
- [ ] Respect current Amazon caching/freshness rules
- [ ] API health dashboard widget

---

# 16. Amazon Associates compliance

- [ ] Verify current Associates disclosure wording/location
- [ ] Verify Creators API terms
- [ ] Verify permitted product image usage
- [ ] Verify pricing display requirements
- [ ] Verify price freshness requirements
- [ ] Verify data caching limits
- [ ] Verify permitted data storage
- [ ] Verify affiliate URL handling/redirect rules
- [ ] Verify trademark/logo usage
- [ ] Verify Amazon branding requirements
- [ ] Add global Associates disclosure
- [ ] Add compliance notes to developer documentation

---

# 17. Filtering and product discovery

- [ ] Public product catalogue route
- [ ] Filter by category
- [ ] Filter by brand
- [ ] Filter by price
- [ ] Filter by editorial score
- [ ] Filter by category-specific specs
- [ ] Sorting
- [ ] Pagination
- [ ] Filter URL/query-state strategy
- [ ] SEO behavior for filtered pages
- [ ] Mobile filter drawer

---

# 18. Site settings

- [x] Settings table
- [ ] General website settings screen
- [ ] Site name
- [ ] Logo settings
- [ ] Contact details
- [ ] Global disclosure text
- [ ] Default SEO settings
- [ ] Social profile settings
- [ ] Homepage settings
- [ ] Analytics IDs
- [ ] Integrations section

---

# 19. Redirect management

- [x] Redirect table
- [ ] Apply redirect lookup before 404
- [ ] Redirect admin list
- [ ] Add redirect
- [ ] Edit redirect
- [ ] Enable/disable redirect
- [ ] 301/302 selector
- [ ] Validate redirect destination
- [ ] Prevent redirect loops
- [ ] Track redirect hits if useful

---

# 20. Performance

- [x] Public frontend renders from local database rather than Amazon API per request
- [x] Amazon boundary designed for import/sync model
- [ ] Add database indexes after query profiling
- [ ] Pagination for large admin lists
- [ ] Pagination for public lists
- [ ] Image lazy loading
- [ ] Responsive image sizes
- [ ] CSS/asset minification strategy
- [ ] Browser caching headers
- [ ] Application caching abstraction
- [ ] Cache category/navigation data
- [ ] Cache sitemap output
- [ ] Lighthouse/PageSpeed pass
- [ ] Core Web Vitals optimization

---

# 21. Testing and QA

## Automated

- [x] GitHub Actions PHP syntax-check workflow added
- [ ] Confirm workflow actually runs successfully on PR
- [ ] Unit-test infrastructure
- [ ] Database integration-test infrastructure
- [ ] Auth tests
- [ ] CSRF tests
- [ ] Product CRUD tests
- [ ] Category CRUD tests
- [ ] Buying-guide CRUD tests
- [ ] Affiliate redirect/click tests
- [ ] Search tests
- [ ] Amazon adapter tests using mocked responses
- [ ] Permission tests

## Manual

- [ ] Clean installation test
- [ ] First-admin setup test
- [ ] Login/logout test
- [ ] Create category test
- [ ] Create manual product test
- [ ] Edit product test
- [ ] Create Top 5 guide test
- [ ] Create Top 10 guide test
- [ ] Publish guide test
- [ ] Public guide rendering test
- [ ] Affiliate CTA redirect test
- [ ] Click analytics test
- [ ] Mobile QA
- [ ] Tablet QA
- [ ] Desktop/browser QA
- [ ] Accessibility QA
- [ ] Error-state QA

---

# 22. Security hardening

- [x] Prepared statements/PDO used for core repository writes
- [x] HTML escape helper
- [x] CSRF protection for current admin writes
- [x] Password hashes rather than plaintext passwords
- [x] `.env` excluded from Git
- [ ] Audit all output escaping
- [ ] Rich-content sanitization
- [ ] Upload hardening
- [ ] URL validation for affiliate/external links
- [ ] Login throttling
- [ ] Session security settings
- [ ] Secret encryption at rest
- [ ] Admin audit trail
- [ ] CSP/security headers
- [ ] Dependency vulnerability process if Composer dependencies are introduced
- [ ] Production error-display disabled
- [ ] Security review before public launch

---

# 23. Phase 1 launch definition

The first production-ready release is considered complete when a non-technical editor can do the following without developer intervention:

- [ ] Log into the admin panel
- [ ] Create/select an AC category
- [ ] Add ten products manually
- [ ] Add product images
- [ ] Add product specifications
- [ ] Add pros/cons
- [ ] Add editorial scores
- [ ] Add Amazon affiliate URLs
- [ ] Create a buying guide titled `Best 10 ACs Under ₹40,000`
- [ ] Select the ten products
- [ ] Rank them 1–10
- [ ] Add guide-specific recommendations
- [ ] Preview the guide
- [ ] Publish the guide
- [ ] See a polished public buying-guide page
- [ ] Click an Amazon CTA and reach the correct affiliate URL
- [ ] See outbound click activity in admin analytics
- [ ] Complete all of the above with Amazon Creators API disabled/unavailable

---

# 24. Phase 2 — Amazon-assisted workflow

- [ ] Add Amazon credentials securely
- [ ] Test API connection
- [ ] Search Amazon from admin
- [ ] Import an Amazon product
- [ ] Populate permitted Amazon fields
- [ ] Add editorial overrides
- [ ] Add imported product to a buying guide
- [ ] Sync imported product later
- [ ] Preserve editorial overrides after synchronization
- [ ] Surface API errors without breaking the public site

---

# 25. Phase 3 / future improvements

- [ ] Automated Top-N product suggestions
- [ ] AI-assisted buying-guide drafts
- [ ] Product scoring algorithms
- [ ] Price history if policy/API permits
- [ ] Automatic comparison generation
- [ ] Related-content recommendations
- [ ] Advanced affiliate analytics
- [ ] Multiple Amazon marketplaces
- [ ] Email newsletter integration
- [ ] Personalized recommendations
- [ ] Product alerts
- [ ] Additional affiliate networks

---

# 26. Immediate next tasks

These are the recommended next implementation order.

1. [ ] Validate PR #1 PHP syntax workflow and fix any failures
2. [ ] Merge/stack this project tracker into the active development line
3. [ ] Implement Brands admin CRUD
4. [ ] Implement specification-definition admin CRUD
5. [ ] Implement category-aware product specification editor
6. [ ] Implement Blog admin CRUD
7. [ ] Implement Amazon settings screen with secrets handled securely
8. [ ] Add redirect handling + redirect admin screen
9. [ ] Add media upload/library foundation
10. [ ] Improve buying-guide builder product selection/ranking UX
11. [ ] Add public category pages and filters
12. [ ] Resolve production 403/document-root configuration before the first development PR is merged to `main`

---

# Notes

- `main` is production. Feature work should normally be developed on branches and merged intentionally.
- Do not make Amazon Creators API access a launch requirement.
- Public templates must operate on the internal product model and should not care whether data originated manually or through Amazon.
- Any live pricing/image/API behavior must be implemented only after checking the current Amazon Associates and Creators API rules.
- Keep this file updated whenever a meaningful feature is completed, removed, deferred, or discovered.
