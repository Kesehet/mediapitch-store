<?php use MediaPitch\Core\Csrf; ?>
<?php $stats=$backfillStats??['total'=>0,'needs_backfill'=>0,'with_link'=>0,'with_asin'=>0,'recent_applied'=>0]; ?>
<section class="panel">
  <div class="panel-head">
    <div>
      <h2>Product backfill</h2>
      <p>Fill missing product data from existing product links, public web evidence and Ollama. Existing populated fields are preserved.</p>
    </div>
    <form method="post" action="<?= e(url('admin/product-tools/backfill-next')) ?>" class="form-actions">
      <?= Csrf::field() ?>
      <input type="hidden" name="limit" value="3">
      <label style="display:flex;align-items:center;gap:6px;margin:0"><input type="checkbox" name="use_ai" value="1" checked> Use Ollama</label>
      <button class="primary-button" type="submit">Backfill next 3</button>
    </form>
  </div>

  <div class="stat-grid" style="margin-bottom:16px">
    <div class="stat-card"><strong><?= (int)$stats['needs_backfill'] ?></strong><span>Need backfill</span></div>
    <div class="stat-card"><strong><?= (int)$stats['with_link'] ?></strong><span>Have source link</span></div>
    <div class="stat-card"><strong><?= (int)$stats['with_asin'] ?></strong><span>Have ASIN</span></div>
    <div class="stat-card"><strong><?= (int)$stats['recent_applied'] ?></strong><span>Fields filled in 7 days</span></div>
  </div>

  <form method="post" action="<?= e(url('admin/product-tools/backfill-selected')) ?>">
    <?= Csrf::field() ?>
    <div class="form-actions" style="justify-content:flex-start;margin-bottom:12px">
      <label style="display:flex;align-items:center;gap:6px;margin:0"><input type="checkbox" name="use_ai" value="1" checked> Use Ollama</label>
      <select name="limit" aria-label="Maximum products">
        <option value="3">Up to 3</option>
        <option value="5">Up to 5</option>
        <option value="10">Up to 10</option>
      </select>
      <button class="secondary-button" type="submit">Backfill selected</button>
      <button class="link-button" type="button" id="select-all-backfill">Select all visible</button>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th></th><th>Product</th><th>Completeness</th><th>Source</th><th>Missing</th></tr></thead>
        <tbody>
        <?php foreach(($backfillCandidates??[]) as $p): ?>
          <?php
            $missing=[];
            if(empty($p['brand_id']))$missing[]='brand';
            if(empty($p['category_id']))$missing[]='category';
            if(empty($p['asin']))$missing[]='ASIN';
            if(empty($p['short_description']))$missing[]='short description';
            if(empty($p['full_description']))$missing[]='full description';
            if(empty($p['main_image_url']))$missing[]='image';
            if(empty($p['features_json']))$missing[]='features';
            if($p['price']===null||$p['price']==='')$missing[]='price';
          ?>
          <tr>
            <td><input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" aria-label="Select <?= e($p['display_title'] ?: $p['title']) ?>"></td>
            <td><strong><?= e($p['display_title'] ?: $p['title']) ?></strong><small><?= e($p['brand_name']??'') ?><?= !empty($p['category_name'])?' · '.e($p['category_name']):'' ?></small></td>
            <td><strong><?= (int)$p['completeness'] ?>%</strong></td>
            <td><?php if(!empty($p['source_url'])):?><a href="<?= e($p['source_url']) ?>" target="_blank" rel="noopener noreferrer">Open link</a><?php else:?><span class="muted">No link</span><?php endif;?></td>
            <td><?= e(implode(', ',$missing)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($backfillCandidates)): ?><tr><td colspan="5" class="empty">No incomplete products found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
</section>

<?php if(!empty($backfillHistory)): ?>
<section class="panel" style="margin-top:1rem">
  <div class="panel-head"><div><h2>Recent enrichment</h2><p>Latest fields filled automatically, with source and confidence.</p></div></div>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Product</th><th>Field</th><th>Source</th><th>Confidence</th><th>When</th></tr></thead><tbody>
  <?php foreach($backfillHistory as $row): ?>
    <tr><td><?= e($row['product_title']) ?></td><td><?= e($row['field_name']) ?></td><td><span class="badge"><?= e($row['source_type']) ?></span></td><td><?= $row['confidence']!==null?e((string)round((float)$row['confidence']*100).'%'):'—' ?></td><td><?= e($row['created_at']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<div class="two-col" style="margin-top:1rem">
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
<section class="panel" style="margin-top:1rem"><h2>Backfill safety rules</h2><ul><li>Existing populated fields are never overwritten.</li><li>Price is accepted only from structured page/API metadata, never invented by Ollama.</li><li>Ollama receives gathered evidence and is instructed to return unknown values empty.</li><li>Brand/category/specification additions are restricted to known CMS structures.</li><li>Every applied field is logged with its source and confidence.</li></ul><p><a href="<?= e(url('admin/products')) ?>">← Back to products</a></p></section>
<script>
(function(){
  const button=document.getElementById('select-all-backfill');
  if(!button)return;
  button.addEventListener('click',()=>{
    const boxes=[...document.querySelectorAll('input[name="product_ids[]"]')];
    const shouldSelect=boxes.some(box=>!box.checked);
    boxes.forEach(box=>box.checked=shouldSelect);
    button.textContent=shouldSelect?'Clear selection':'Select all visible';
  });
})();
</script>
