<?php use MediaPitch\Core\Csrf; ?>
<div class="two-col">
  <section class="panel">
    <div class="panel-head"><div><h2>Export products</h2><p>Download a CSV snapshot of editable product fields.</p></div></div>
    <p>The export includes IDs, title/slug, source/ASIN, category/brand IDs, pricing, score, links, image URL and active state.</p>
    <a class="primary-button" href="<?= e(url('admin/product-tools/export.csv')) ?>">Download product CSV</a>
  </section>
  <section class="panel">
    <div class="panel-head"><div><h2>Import products</h2><p>Create or update products from CSV.</p></div></div>
    <form method="post" action="<?= e(url('admin/product-tools/import')) ?>" enctype="multipart/form-data" class="admin-form">
      <?= Csrf::field() ?>
      <label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
      <p class="muted">Use the exported CSV as the safest template. Existing rows update only when a valid existing <code>id</code> is supplied. New rows default inactive unless <code>active</code> is explicitly true/1/yes/active.</p>
      <button class="primary-button" type="submit">Import CSV</button>
    </form>
  </section>
</div>
<section class="panel" style="margin-top:1rem"><h2>CSV safety rules</h2><ul><li>Maximum 5 MB / 1000 data rows per upload.</li><li>Duplicate slugs and ASINs are blocked.</li><li>Category and brand IDs must already exist.</li><li>URLs and numeric fields are validated before each row is saved.</li><li>Invalid rows are skipped and reported; valid rows still import.</li></ul><p><a href="<?= e(url('admin/products')) ?>">← Back to products</a></p></section>
