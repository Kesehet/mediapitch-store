<?php use MediaPitch\Core\Csrf; use MediaPitch\Services\ContentVisibility; $c=$comparison??[]; $selected=array_map(static fn($r)=>(int)$r['product_id'],$c['products']??[]); ?>
<form method="post" action="<?= e(url('admin/comparisons/save')) ?>" class="panel form-panel"><?= Csrf::field() ?><?php if(!empty($c['id'])):?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><?php endif;?>
<div class="form-grid">
<label class="span-2">Title<input name="title" required value="<?= e($c['title']??'') ?>"></label>
<label>Slug<input name="slug" required value="<?= e($c['slug']??'') ?>"></label>
<label>Category<select name="category_id"><option value="">—</option><?php foreach($categories as $category):?><option value="<?= (int)$category['id'] ?>" <?= (int)($c['category_id']??0)===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach;?></select></label>
<label class="span-2">Short summary<textarea name="excerpt" rows="3"><?= e($c['excerpt']??'') ?></textarea></label>
<label class="span-2">Editorial verdict / comparison notes<textarea name="body" rows="8"><?= e($c['body']??'') ?></textarea></label>
<label class="span-2">Featured image URL<input type="url" name="featured_image_url" value="<?= e($c['featured_image_url']??'') ?>"></label>
</div>
<div class="panel" style="margin-top:1rem"><div class="panel-head"><div><h3>Products to compare</h3><p>Select at least two. Order here becomes the left-to-right order on the comparison page.</p></div></div>
<div id="comparison-products">
<?php $rows=$c['products']??[]; if(!$rows)$rows=[['product_id'=>0],['product_id'=>0]]; foreach($rows as $row):?>
<div class="form-grid comparison-product-row"><label class="span-2">Product<select name="product_id[]" required><option value="">Select product</option><?php foreach($productOptions as $p):?><option value="<?= (int)$p['id'] ?>" <?= (int)$row['product_id']===(int)$p['id']?'selected':'' ?>><?= e($p['title']) ?></option><?php endforeach;?></select></label><div><button type="button" class="secondary-button remove-product">Remove</button></div></div>
<?php endforeach;?>
</div><button type="button" class="secondary-button" id="add-product">Add product</button></div>
<div class="form-grid" style="margin-top:1rem">
<label>Status<select name="status"><option value="draft" <?= ($c['status']??'draft')==='draft'?'selected':'' ?>>Draft</option><option value="scheduled" <?= ($c['status']??'')==='scheduled'?'selected':'' ?>>Scheduled</option><option value="published" <?= ($c['status']??'')==='published'?'selected':'' ?>>Published</option></select></label>
<label>Publish date/time <small><?= e(ContentVisibility::editorialTimezone()->getName()) ?></small><input type="datetime-local" name="published_at" value="<?= e(ContentVisibility::publishAtForInput($c['published_at'] ?? null)) ?>"></label>
<label class="span-2">SEO title<input name="seo_title" value="<?= e($c['seo_title']??'') ?>"></label>
<label class="span-2">Meta description<textarea name="meta_description" rows="3"><?= e($c['meta_description']??'') ?></textarea></label>
<label class="span-2">Canonical URL<input type="url" name="canonical_url" value="<?= e($c['canonical_url']??'') ?>"></label>
</div>
<label class="check"><input type="checkbox" name="robots_index" value="1" <?= !isset($c['robots_index'])||!empty($c['robots_index'])?'checked':'' ?>> Allow search engines to index this comparison</label>
<div class="form-actions"><a class="secondary-button" href="<?= e(url('admin/comparisons')) ?>">Cancel</a><?php if(!empty($c['id'])&&($c['status']??'')==='published'):?><a class="secondary-button" href="<?= e(url('compare/'.$c['slug'])) ?>" target="_blank">Preview</a><?php endif;?><button class="primary-button">Save comparison</button></div></form>
<template id="product-row-template"><div class="form-grid comparison-product-row"><label class="span-2">Product<select name="product_id[]" required><option value="">Select product</option><?php foreach($productOptions as $p):?><option value="<?= (int)$p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach;?></select></label><div><button type="button" class="secondary-button remove-product">Remove</button></div></div></template>
<script>
(function(){const box=document.getElementById('comparison-products'),tpl=document.getElementById('product-row-template');document.getElementById('add-product').addEventListener('click',()=>box.appendChild(tpl.content.cloneNode(true)));box.addEventListener('click',e=>{if(e.target.classList.contains('remove-product')&&box.querySelectorAll('.comparison-product-row').length>2)e.target.closest('.comparison-product-row').remove();});})();
</script>