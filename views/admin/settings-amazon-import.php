<?php use MediaPitch\Core\Csrf; ?>
<section class="panel">
  <div class="panel-head"><div><h2>Import from Amazon</h2><p>Search the configured marketplace, review the results, then import a product as an inactive draft.</p></div><a class="secondary-button" href="<?= e(url('admin/settings/amazon')) ?>">Amazon settings</a></div>
  <?php if(empty($settings['enabled'])):?><div class="flash error">Amazon Creators API is disabled. Enable it in Amazon Settings before searching.</div><?php endif;?>
  <form method="post" action="<?= e(url('admin/settings/amazon/search')) ?>" class="form-grid" style="max-width:800px">
    <?= Csrf::field() ?>
    <label class="span-2">Amazon search<input type="search" name="q" required value="<?= e($query??'') ?>" placeholder="e.g. air conditioner, laptop, children's book"></label>
    <div><button class="primary-button" <?= empty($settings['enabled'])?'disabled':'' ?>>Search Amazon</button></div>
  </form>
  <p class="muted">Marketplace: <?= e($settings['marketplace']??'') ?> · Amazon returns up to 10 items per search.</p>
</section>

<?php if(!empty($results)):?><section class="panel"><div class="panel-head"><div><h2>Search results</h2><p>Importing an existing ASIN refreshes Amazon-owned data while preserving MediaPitch editorial fields.</p></div></div><div style="display:grid;gap:16px">
<?php foreach($results as $item):?>
<article style="display:grid;grid-template-columns:120px 1fr;gap:18px;border:1px solid #e5e7eb;border-radius:12px;padding:16px">
  <div><?php if(!empty($item['image'])):?><img src="<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>" style="width:100%;height:120px;object-fit:contain"><?php else:?><div class="image-placeholder">No image</div><?php endif;?></div>
  <div>
    <span class="badge">ASIN <?= e($item['asin']) ?></span>
    <h3 style="margin:.5rem 0"><?= e($item['title']) ?></h3>
    <?php if(!empty($item['display_price'])):?><p><strong><?= e($item['display_price']) ?></strong> <small>Amazon API price returned for this search</small></p><?php endif;?>
    <?php if(!empty($item['availability'])):?><p class="muted"><?= e($item['availability']) ?></p><?php endif;?>
    <?php if(!empty($item['features'])):?><ul><?php foreach(array_slice($item['features'],0,3) as $feature):?><li><?= e((string)$feature) ?></li><?php endforeach;?></ul><?php endif;?>
    <form method="post" action="<?= e(url('admin/settings/amazon/import-item')) ?>" class="form-grid" style="margin-top:12px">
      <?= Csrf::field() ?><input type="hidden" name="asin" value="<?= e($item['asin']) ?>">
      <label>MediaPitch category<select name="category_id"><option value="">Leave unassigned</option><?php foreach($categories as $category):?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach;?></select></label>
      <div style="align-self:end"><button class="primary-button">Import / refresh</button><?php if(!empty($item['detail_url'])):?><a class="secondary-button" href="<?= e($item['detail_url']) ?>" target="_blank" rel="noopener noreferrer">View Amazon ↗</a><?php endif;?></div>
    </form>
  </div>
</article>
<?php endforeach;?>
</div></section><?php endif;?>
