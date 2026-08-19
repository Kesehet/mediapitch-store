<?php
use MediaPitch\Core\Auth;
$helpIsAdmin=Auth::isAdministrator();
$helpCanCatalog=Auth::canManageProducts();
$helpCanContent=Auth::canEditContent();
$helpCanMedia=Auth::canUploadMedia();
?>
<div class="help-shell" data-help-root>
  <section class="help-hero">
    <div>
      <span class="help-eyebrow">MediaPitch CMS handbook</span>
      <h2>How to use the CMS</h2>
      <p>Step-by-step instructions for everyday work: adding products, publishing articles, building guides and comparisons, managing Amazon data, SEO, media and the homepage.</p>
    </div>
    <div class="help-search-wrap">
      <label for="help-search">Search this documentation</label>
      <input id="help-search" type="search" placeholder="Try: product, blog, Amazon, SEO…" data-help-search>
      <small data-help-count>Showing all help topics</small>
    </div>
  </section>

  <section class="help-start panel" data-help-section data-help-keywords="start overview workflow dashboard">
    <div class="help-section-title"><span class="help-number">1</span><div><h2>Start here</h2><p>The normal MediaPitch publishing workflow.</p></div></div>
    <div class="help-flow">
      <div><strong>1. Prepare</strong><span>Create categories, brands, specifications and media first when needed.</span></div>
      <div><strong>2. Create</strong><span>Add the product or editorial content as a draft.</span></div>
      <div><strong>3. Review</strong><span>Check images, links, SEO fields, spelling and product relationships.</span></div>
      <div><strong>4. Publish</strong><span>Activate the product or publish/schedule editorial content.</span></div>
      <div><strong>5. Verify</strong><span>Use “View site ↗” and confirm the public page looks correct.</span></div>
    </div>
    <div class="help-callout"><strong>Good habit:</strong> create and review as a draft first. Publish only after checking the public-facing information.</div>
  </section>

  <?php if($helpCanCatalog): ?>
  <section id="help-products" class="panel help-topic" data-help-section data-help-keywords="product add edit amazon asin price image gallery specifications affiliate active archive restore duplicate">
    <div class="help-section-title"><span class="help-number">2</span><div><h2>Add or edit a product</h2><p>Products are the central records used by guides, comparisons, reviews, search and homepage merchandising.</p></div><a class="secondary-button" href="<?= e(url('admin/products')) ?>">Open Products</a></div>
    <div class="help-two-col">
      <div>
        <h3>To add a product</h3>
        <ol class="help-steps">
          <li>Go to <strong>Products</strong> and click <strong>+ Add product</strong>.</li>
          <li>Enter the <strong>Title</strong>. The slug is the URL-friendly name; keep it short and readable.</li>
          <li>Choose a <strong>Category</strong> and <strong>Brand</strong> when applicable.</li>
          <li>Select the source: normally <strong>Manual</strong>, or use the Amazon import screen for Amazon API products.</li>
          <li>Add the short/full description, main image and gallery images.</li>
          <li>Add features, pros, cons, score, “best for” label and editorial notes as needed.</li>
          <li>For manual products, enter price/currency and the destination or affiliate URL where appropriate.</li>
          <li>Complete the specification fields shown for the selected catalog.</li>
          <li>Leave <strong>Active</strong> off while reviewing. Save the product.</li>
          <li>Open the public preview/page, check it, then activate the product.</li>
        </ol>
        <h3>Editing an Amazon product</h3>
        <p>Amazon-imported fields can be refreshed from Amazon. If you intentionally replace an Amazon-controlled field with editorial content, use its manual override control so a future refresh does not overwrite your change.</p>
        <div class="help-callout warning"><strong>Price rule:</strong> Amazon pricing/availability is time-sensitive. Do not manually present stale Amazon prices as current API pricing.</div>
      </div>
      <div class="help-visual" aria-label="Product editor visual guide">
        <div class="help-visual-bar">Product editor</div>
        <div class="help-visual-row"><b>① Title + slug</b><span>Name and public URL</span></div>
        <div class="help-visual-row"><b>② Category + brand</b><span>Where the product belongs</span></div>
        <div class="help-visual-box tall"><b>③ Editorial content</b><span>Description · features · pros · cons</span></div>
        <div class="help-visual-row"><b>④ Images</b><span>Main image + gallery</span></div>
        <div class="help-visual-row"><b>⑤ Specifications</b><span>Structured comparison data</span></div>
        <div class="help-visual-actions"><span>Save draft</span><strong>Activate after review</strong></div>
      </div>
    </div>
    <h3>Product maintenance</h3>
    <ul class="help-bullets">
      <li><strong>Archive</strong> hides a product without destroying its record.</li>
      <li><strong>Restore</strong> brings an archived product back.</li>
      <li><strong>Duplicate</strong> creates an inactive copy to use as a starting point.</li>
      <li>Changing a product slug automatically creates a redirect from the old product URL.</li>
    </ul>
  </section>

  <section id="help-categories" class="panel help-topic" data-help-section data-help-keywords="categories category nested parent image seo archive restore">
    <div class="help-section-title"><span class="help-number">3</span><div><h2>Categories</h2><p>Organize products and create browsable catalog sections.</p></div><a class="secondary-button" href="<?= e(url('admin/categories')) ?>">Open Categories</a></div>
    <ol class="help-steps">
      <li>Open <strong>Categories</strong> and create or edit a category.</li>
      <li>Enter its name and slug. Choose a <strong>Parent</strong> to make it a subcategory.</li>
      <li>Add a category image through the Media Library or image field.</li>
      <li>Complete SEO title/description where needed.</li>
      <li>Keep the category active if it should be publicly available.</li>
    </ol>
    <p>Use a simple hierarchy. For example: <strong>Electronics → Headphones → Wireless Headphones</strong>. Avoid creating multiple categories that mean the same thing.</p>
  </section>

  <section id="help-brands" class="panel help-topic" data-help-section data-help-keywords="brand brands logo website seo archive">
    <div class="help-section-title"><span class="help-number">4</span><div><h2>Brands</h2><p>Create reusable manufacturer/brand pages and attach products to them.</p></div><a class="secondary-button" href="<?= e(url('admin/brands')) ?>">Open Brands</a></div>
    <ol class="help-steps"><li>Open <strong>Brands</strong>.</li><li>Enter the brand name and slug.</li><li>Add its official website and logo if available.</li><li>Add description/SEO information when the brand should have a useful public landing page.</li><li>Save, then assign products to the brand from the Product editor.</li></ol>
  </section>

  <section id="help-specs" class="panel help-topic" data-help-section data-help-keywords="specification specs comparison fields number boolean select text unit filter">
    <div class="help-section-title"><span class="help-number">5</span><div><h2>Specifications</h2><p>Structured facts used for product details, filters and comparisons.</p></div><a class="secondary-button" href="<?= e(url('admin/specifications')) ?>">Open Specifications</a></div>
    <ol class="help-steps"><li>Create a specification such as <strong>Screen size</strong>, <strong>Weight</strong> or <strong>Wireless</strong>.</li><li>Choose the correct field type: text, number, boolean or select/options.</li><li>Add a unit where useful, such as <code>kg</code>, <code>inch</code> or <code>GB</code>.</li><li>Set options for controlled-choice specifications.</li><li>Fill the specification values on each relevant Product page.</li></ol>
    <div class="help-callout"><strong>Why this matters:</strong> do not hide important comparable facts only inside prose. Structured specifications make comparison tables and filtering much more reliable.</div>
  </section>
  <?php endif; ?>

  <?php if($helpCanMedia): ?>
  <section id="help-media" class="panel help-topic" data-help-section data-help-keywords="media image upload alt text thumbnail webp category image gallery delete">
    <div class="help-section-title"><span class="help-number">6</span><div><h2>Media Library</h2><p>Upload and reuse product, category and editorial images.</p></div><a class="secondary-button" href="<?= e(url('admin/media')) ?>">Open Media</a></div>
    <ol class="help-steps">
      <li>Open <strong>Media</strong> and upload an image.</li>
      <li>Add descriptive <strong>alt text</strong> describing what is visible in the image.</li>
      <li>Copy/select the stored image when editing a product, brand, category or article.</li>
      <li>Use the Media page to assign a selected image directly to a category when helpful.</li>
    </ol>
    <p>The CMS validates image types and size. When the server supports it, images/thumbnails are optimized automatically. A media item cannot be safely deleted while the CMS detects that it is still in use.</p>
  </section>
  <?php endif; ?>

  <?php if($helpCanCatalog): ?>
  <section id="help-homepage" class="panel help-topic" data-help-section data-help-keywords="homepage picks featured deals merchandising home products">
    <div class="help-section-title"><span class="help-number">7</span><div><h2>Homepage Picks</h2><p>Control manually featured products and the deals section.</p></div><a class="secondary-button" href="<?= e(url('admin/merchandising')) ?>">Open Homepage Picks</a></div>
    <ol class="help-steps"><li>Choose products for the <strong>Featured</strong> area.</li><li>Choose products for the <strong>Deals</strong> area.</li><li>Set the deals section title.</li><li>Save and verify the homepage.</li></ol>
    <div class="help-callout warning"><strong>Amazon note:</strong> featuring a product is fine; do not use the CMS to create unsupported price-alert/tracking claims or imply that an old Amazon price is current.</div>
  </section>
  <?php endif; ?>

  <?php if($helpCanContent): ?>
  <section id="help-blog" class="panel help-topic" data-help-section data-help-keywords="blog article post publish schedule tags seo featured image editor draft">
    <div class="help-section-title"><span class="help-number">8</span><div><h2>Create a blog article</h2><p>For editorial posts, explainers, news-style evergreen content and other non-guide articles.</p></div><a class="secondary-button" href="<?= e(url('admin/blog')) ?>">Open Blog</a></div>
    <div class="help-two-col">
      <div>
        <ol class="help-steps">
          <li>Go to <strong>Blog → New Article</strong>.</li>
          <li>Enter the title and a short, clean slug.</li>
          <li>Choose a relevant category.</li>
          <li>Add the excerpt and article body using clear H2/H3 headings.</li>
          <li>Add a featured image and useful alt text in Media.</li>
          <li>Add relevant tags, separated by commas.</li>
          <li>Complete the SEO title, meta description and canonical URL only when needed.</li>
          <li>Keep <strong>Robots index</strong> enabled for normal public articles.</li>
          <li>Save as <strong>Draft</strong>, or choose <strong>Scheduled/Published</strong> if your role permits it.</li>
          <li>Review the public article after publishing.</li>
        </ol>
        <div class="help-callout"><strong>SEO tip:</strong> write for the reader first. Use one clear topic, descriptive headings, a useful introduction, relevant internal links and only tags that genuinely describe the article.</div>
      </div>
      <div class="help-visual" aria-label="Blog editor visual guide">
        <div class="help-visual-bar">Blog editor</div>
        <div class="help-visual-row"><b>① Headline</b><span>Title + slug</span></div>
        <div class="help-visual-row"><b>② Summary</b><span>Excerpt</span></div>
        <div class="help-visual-box x-tall"><b>③ Article body</b><span>H2/H3 sections · links · product embeds</span></div>
        <div class="help-visual-row"><b>④ Discoverability</b><span>Image · tags · SEO fields</span></div>
        <div class="help-visual-actions"><span>Draft / Schedule</span><strong>Publish</strong></div>
      </div>
    </div>
  </section>

  <section id="help-guides" class="panel help-topic" data-help-section data-help-keywords="buying guide guide products ranking best for score recommendation faq how selected publish">
    <div class="help-section-title"><span class="help-number">9</span><div><h2>Create a buying guide</h2><p>Use guides for ranked recommendations such as “Best laptops for students”.</p></div><a class="secondary-button" href="<?= e(url('admin/guides')) ?>">Open Buying Guides</a></div>
    <ol class="help-steps"><li>Create the products first so they can be selected in the guide.</li><li>Open <strong>Buying Guides → New Buying Guide</strong>.</li><li>Write the title, slug, excerpt and main guide content.</li><li>Select and order the recommended products.</li><li>For each product, add ranking position, score, “best for” label, recommendation text and CTA text where appropriate.</li><li>Add “How we selected” methodology and FAQs when useful.</li><li>Complete SEO/image fields.</li><li>Save as Draft, review, then schedule/publish.</li></ol>
    <div class="help-visual horizontal"><div class="help-visual-bar">Guide product ranking</div><div class="help-rank-row"><b>#1</b><span>Best overall product</span><em>Score · Best for · Recommendation</em></div><div class="help-rank-row"><b>#2</b><span>Alternative product</span><em>Score · Best for · Recommendation</em></div><div class="help-rank-row"><b>#3</b><span>Budget/specialist option</span><em>Score · Best for · Recommendation</em></div></div>
  </section>

  <section id="help-reviews" class="panel help-topic" data-help-section data-help-keywords="review product rating pros cons verdict schema publish">
    <div class="help-section-title"><span class="help-number">10</span><div><h2>Create a product review</h2><p>A focused editorial review of one product.</p></div><a class="secondary-button" href="<?= e(url('admin/reviews')) ?>">Open Reviews</a></div>
    <ol class="help-steps"><li>Create/select the reviewed product first.</li><li>Open <strong>Reviews</strong> and create a new review.</li><li>Choose the product and write the review title, summary/body and verdict.</li><li>Add rating/score information only when it reflects your editorial methodology.</li><li>Add featured image and SEO information.</li><li>Save as draft, check the product association, then publish.</li></ol>
  </section>

  <section id="help-comparisons" class="panel help-topic" data-help-section data-help-keywords="comparison compare versus vs products table specs winner publish">
    <div class="help-section-title"><span class="help-number">11</span><div><h2>Create a comparison</h2><p>Compare two or more existing products using their structured specifications.</p></div><a class="secondary-button" href="<?= e(url('admin/comparisons')) ?>">Open Comparisons</a></div>
    <ol class="help-steps"><li>Make sure each product has accurate specification values.</li><li>Open <strong>Comparisons</strong> and create a comparison.</li><li>Choose the products to compare.</li><li>Write the introduction, analysis and conclusion/winner guidance.</li><li>Check the generated specification matrix for missing or inconsistent values.</li><li>Add image/SEO fields, save as draft, then publish after review.</li></ol>
  </section>
  <?php endif; ?>

  <?php if($helpIsAdmin): ?>
  <section id="help-redirects" class="panel help-topic" data-help-section data-help-keywords="redirect redirects url old path new url seo 301">
    <div class="help-section-title"><span class="help-number">12</span><div><h2>Redirects</h2><p>Send an old URL to the correct new location.</p></div><a class="secondary-button" href="<?= e(url('admin/redirects')) ?>">Open Redirects</a></div>
    <ol class="help-steps"><li>Enter the old <strong>From path</strong>, for example <code>/old-page</code>.</li><li>Enter the new destination.</li><li>Use a permanent redirect for a permanently moved page.</li><li>Save, then test the old URL in a private/incognito window.</li></ol>
    <p>Product, guide and blog slug changes already create redirects in supported editor flows. Add manual redirects when migrating or consolidating other URLs.</p>
  </section>

  <section id="help-settings" class="panel help-topic" data-help-section data-help-keywords="website settings site name tagline disclosure homepage sections settings">
    <div class="help-section-title"><span class="help-number">13</span><div><h2>Website Settings</h2><p>Global site identity, disclosure and homepage-section switches.</p></div><a class="secondary-button" href="<?= e(url('admin/settings/site')) ?>">Open Website Settings</a></div>
    <ol class="help-steps"><li>Set the site name and tagline.</li><li>Review the affiliate disclosure shown publicly.</li><li>Enable or disable the homepage content sections you want displayed.</li><li>Save and verify both desktop and mobile homepage layouts.</li></ol>
  </section>

  <section id="help-amazon" class="panel help-topic" data-help-section data-help-keywords="amazon creators api marketplace profile credentials import asin search refresh sync partner tag override">
    <div class="help-section-title"><span class="help-number">14</span><div><h2>Amazon Creators API</h2><p>Connect marketplaces, import products and refresh Amazon-controlled data.</p></div><a class="secondary-button" href="<?= e(url('admin/settings/amazon')) ?>">Open Amazon Settings</a></div>
    <div class="help-two-col">
      <div>
        <h3>Connect a marketplace</h3>
        <ol class="help-steps"><li>Open <strong>Amazon Settings</strong>.</li><li>Create/edit the marketplace profile, e.g. <code>www.amazon.in</code>.</li><li>Enter the partner tag and Creators API credentials.</li><li>Enable the profile and save it.</li><li>Use <strong>Test connection</strong>. Do not continue until authentication succeeds.</li></ol>
        <h3>Import a product</h3>
        <ol class="help-steps"><li>Open the Amazon import/search screen.</li><li>Search Amazon or supply the ASIN.</li><li>Choose the correct local category.</li><li>Import the product. Imports are designed to be reviewed before activation.</li><li>Edit the product's editorial fields and activate only after review.</li></ol>
        <h3>Refresh</h3><p>Use refresh to update stale API-controlled product data. Scheduled CLI/cron refresh can perform this automatically for enabled configured marketplaces.</p>
      </div>
      <div class="help-visual">
        <div class="help-visual-bar">Amazon workflow</div>
        <div class="help-visual-step"><b>① Marketplace profile</b><span>Partner tag + encrypted credentials</span></div>
        <div class="help-visual-arrow">↓</div>
        <div class="help-visual-step"><b>② Test connection</b><span>Confirm authentication</span></div>
        <div class="help-visual-arrow">↓</div>
        <div class="help-visual-step"><b>③ Search / ASIN import</b><span>Creates or refreshes product data</span></div>
        <div class="help-visual-arrow">↓</div>
        <div class="help-visual-step"><b>④ Editorial review</b><span>Descriptions · scoring · overrides</span></div>
        <div class="help-visual-arrow">↓</div>
        <div class="help-visual-step"><b>⑤ Activate + refresh</b><span>Keep Amazon-controlled data fresh</span></div>
      </div>
    </div>
    <div class="help-callout warning"><strong>Never paste Amazon credentials into articles, products or support messages.</strong> Credentials belong only in Amazon Settings and are stored encrypted.</div>
  </section>

  <section id="help-users" class="panel help-topic" data-help-section data-help-keywords="users roles administrator editor writer seo manager permissions password">
    <div class="help-section-title"><span class="help-number">15</span><div><h2>Users and roles</h2><p>Give each person only the access they need.</p></div><a class="secondary-button" href="<?= e(url('admin/users')) ?>">Open Users</a></div>
    <div class="help-role-grid"><div><strong>Administrator</strong><span>Full CMS, settings, users, audit and integrations.</span></div><div><strong>Editor</strong><span>Catalog and editorial management/publishing.</span></div><div><strong>Writer</strong><span>Editorial authoring within permitted publishing controls.</span></div><div><strong>SEO Manager</strong><span>SEO-oriented content/site work according to configured permissions.</span></div></div>
    <p>Do not share accounts. Create a separate user for each person so the Audit Log can show who changed what.</p>
  </section>

  <section id="help-analytics" class="panel help-topic" data-help-section data-help-keywords="analytics clicks affiliate search queries audit log history changes">
    <div class="help-section-title"><span class="help-number">16</span><div><h2>Analytics and Audit Log</h2><p>Understand usage and trace administrative changes.</p></div><div class="inline-actions"><a class="secondary-button" href="<?= e(url('admin/analytics')) ?>">Analytics</a><a class="secondary-button" href="<?= e(url('admin/audit')) ?>">Audit Log</a></div></div>
    <ul class="help-bullets"><li><strong>Analytics</strong> shows privacy-light search activity and affiliate click information available to the CMS.</li><li><strong>Audit Log</strong> records important administrative mutations, such as product edits, publishing actions and settings changes.</li><li>Use these tools to diagnose “who changed this?” and to understand which product links/content users engage with.</li></ul>
  </section>
  <?php endif; ?>

  <section id="help-seo" class="panel help-topic" data-help-section data-help-keywords="seo title meta description canonical robots index slug schema structured data internal links discover">
    <div class="help-section-title"><span class="help-number">17</span><div><h2>SEO fields: what they mean</h2><p>A practical guide to the fields that appear throughout the CMS.</p></div></div>
    <div class="help-definition-grid">
      <div><strong>Slug</strong><span>The readable URL segment. Keep it short, lowercase and descriptive.</span></div>
      <div><strong>SEO title</strong><span>The search/browser title. Use a clear human-readable title rather than keyword stuffing.</span></div>
      <div><strong>Meta description</strong><span>A concise summary of why the page is useful. It may be used as a search-result snippet.</span></div>
      <div><strong>Canonical URL</strong><span>Use when another URL should be treated as the preferred version. Leave blank for ordinary self-canonical pages unless the form indicates otherwise.</span></div>
      <div><strong>Robots index</strong><span>Turn on for pages intended for search engines. Turn off for content you intentionally do not want indexed.</span></div>
      <div><strong>Internal links</strong><span>Link naturally to relevant products, categories, guides and articles so readers can continue their journey.</span></div>
    </div>
  </section>

  <section id="help-publishing" class="panel help-topic" data-help-section data-help-keywords="publish checklist qa quality control draft scheduled published active verify">
    <div class="help-section-title"><span class="help-number">18</span><div><h2>Before you publish: 60-second checklist</h2><p>Use this every time.</p></div></div>
    <div class="help-check-grid"><label><input type="checkbox"> Title and spelling checked</label><label><input type="checkbox"> Correct category/brand/product relationships</label><label><input type="checkbox"> Main/featured image loads</label><label><input type="checkbox"> Image alt text is useful</label><label><input type="checkbox"> Links and affiliate CTA work</label><label><input type="checkbox"> SEO title/meta are sensible</label><label><input type="checkbox"> Price/API information is current where applicable</label><label><input type="checkbox"> Public page checked on mobile/desktop</label></div>
  </section>

  <section id="help-troubleshooting" class="panel help-topic" data-help-section data-help-keywords="troubleshooting error missing table database image save draft not showing publish cache login permissions">
    <div class="help-section-title"><span class="help-number">19</span><div><h2>Troubleshooting</h2><p>Common questions before escalating to a developer.</p></div></div>
    <details><summary>My new product is not visible publicly</summary><p>Check that the product is <strong>Active</strong>, has a valid slug, and is not archived. If it is Amazon-sourced, also confirm the required product information is present.</p></details>
    <details><summary>My article/guide is not visible</summary><p>Check its status. Draft content is not public. Scheduled content appears only after its publish time. Confirm the published date/time and indexing setting.</p></details>
    <details><summary>An image is missing</summary><p>Open Media and make sure the file exists. Check that the selected image path/URL is still attached to the content. Re-select it if necessary.</p></details>
    <details><summary>I cannot see a menu item</summary><p>Menu visibility depends on your role. Ask an administrator to confirm your account permissions rather than sharing somebody else's login.</p></details>
    <details><summary>Amazon import or refresh fails</summary><p>Open Amazon Settings, confirm the intended marketplace is enabled and use Test connection. Check that credentials and partner tag are configured for that profile.</p></details>
    <details><summary>I see a database/schema error</summary><p>This is a deployment/maintenance issue rather than an editorial problem. Do not repeatedly recreate content. Ask the technical administrator to run the documented database deployment and smoke test.</p></details>
  </section>

  <section class="help-footer-card">
    <div><strong>Still unsure?</strong><p>Save the item as a draft and ask an editor/administrator before publishing. Drafts are safer than guessing.</p></div>
    <a class="primary-button" href="<?= e(url('admin')) ?>">Back to Dashboard</a>
  </section>
</div>
<script src="/assets/admin-help.js" defer></script>
