<?php
use MediaPitch\Core\Csrf;
$p=$product ?? [];
$decode=function($v){$a=json_decode((string)$v,true);return is_array($a)?implode("\n",$a):'';};
$currentCategory=(int)($p['category_id'] ?? 0);
?>
<form method="post" action="<?= e(url('admin/products/save')) ?>" class="panel form-panel"><?= Csrf::field() ?><?php if(!empty($p['id'])):?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?php endif; ?>
<div class="form-grid"><label class="span-2">Product title<input name="title" required value="<?= e($p['title'] ?? '') ?>"></label><label class="span-2">Display title<input name="display_title" value="<?= e($p['display_title'] ?? '') ?>"></label><label>Slug<input name="slug" required value="<?= e($p['slug'] ?? '') ?>"></label><label>Source<select name="source"><option value="manual" <?= ($p['source']??'manual')==='manual'?'selected':'' ?>>Manual</option><option value="amazon_api" <?= ($p['source']??'')==='amazon_api'?'selected':'' ?>>Amazon API</option><option value="hybrid" <?= ($p['source']??'')==='hybrid'?'selected':'' ?>>API + manual overrides</option></select></label><label>Category<select name="category_id" id="product-category"><option value="">—</option><?php foreach($categories as $c):?><option value="<?= (int)$c['id'] ?>" <?= (int)($p['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach;?></select></label><label>Brand<select name="brand_id"><option value="">—</option><?php foreach($brands as $b):?><option value="<?= (int)$b['id'] ?>" <?= (int)($p['brand_id']??0)===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach;?></select></label><label>ASIN<input name="asin" value="<?= e($p['asin'] ?? '') ?>"></label><label>Best-for label<input name="best_for_label" value="<?= e($p['best_for_label'] ?? '') ?>"></label><label>Price<input type="number" step="0.01" name="price" value="<?= e(isset($p['price'])?(string)$p['price']:'') ?>"></label><label>Previous price<input type="number" step="0.01" name="previous_price" value="<?= e(isset($p['previous_price'])?(string)$p['previous_price']:'') ?>"></label><label>Currency<input maxlength="3" name="currency" value="<?= e($p['currency'] ?? 'INR') ?>"></label><label>Score<input type="number" min="0" max="10" step="0.1" name="custom_score" value="<?= e(isset($p['custom_score'])?(string)$p['custom_score']:'') ?>"></label><label class="span-2">Main image URL<input type="url" name="main_image_url" value="<?= e($p['main_image_url'] ?? '') ?>"></label><label class="span-2">Amazon URL<input type="url" name="amazon_url" value="<?= e($p['amazon_url'] ?? '') ?>"></label><label class="span-2">Affiliate URL<input type="url" name="affiliate_url" value="<?= e($p['affiliate_url'] ?? '') ?>"></label><label class="span-2">Short description<textarea name="short_description" rows="3"><?= e($p['short_description'] ?? '') ?></textarea></label><label class="span-2">Full description<textarea name="full_description" rows="6"><?= e($p['full_description'] ?? '') ?></textarea></label><label>Features <small>one per line</small><textarea name="features" rows="6"><?= e($decode($p['features_json'] ?? null)) ?></textarea></label><label>Pros <small>one per line</small><textarea name="pros" rows="6"><?= e($decode($p['pros_json'] ?? null)) ?></textarea></label><label>Cons <small>one per line</small><textarea name="cons" rows="6"><?= e($decode($p['cons_json'] ?? null)) ?></textarea></label><label>Editorial notes<textarea name="editorial_notes" rows="6"><?= e($p['editorial_notes'] ?? '') ?></textarea></label></div>

<?php if (!empty($specDefinitions)): ?>
<section class="spec-editor-block">
  <div class="panel-head"><div><h2>Category specifications</h2><p class="muted">Fields change automatically when you select a category.</p></div><a href="<?= e(url('admin/specifications')) ?>">Manage definitions</a></div>
  <div class="form-grid" id="product-spec-fields">
    <?php foreach ($specDefinitions as $definition):
      $id=(int)$definition['id'];
      $stored=$specValues[$id] ?? [];
      $value=$stored['value_text'] ?? ($stored['value_number'] ?? ($stored['value_boolean'] ?? ''));
      $options=json_decode((string)($definition['options_json'] ?? ''),true); if(!is_array($options))$options=[];
    ?>
      <label class="spec-field" data-category="<?= (int)$definition['category_id'] ?>">
        <?= e($definition['name']) ?><?php if(!empty($definition['unit'])): ?> <small>(<?= e($definition['unit']) ?>)</small><?php endif; ?>
        <?php if ($definition['data_type']==='number'): ?>
          <input type="number" step="any" name="spec[<?= $id ?>]" value="<?= e((string)$value) ?>">
        <?php elseif ($definition['data_type']==='boolean'): ?>
          <select name="spec[<?= $id ?>]"><option value="">—</option><option value="1" <?= (string)$value==='1'?'selected':'' ?>>Yes</option><option value="0" <?= (string)$value==='0'?'selected':'' ?>>No</option></select>
        <?php elseif ($definition['data_type']==='select'): ?>
          <select name="spec[<?= $id ?>]"><option value="">—</option><?php foreach($options as $option):?><option value="<?= e((string)$option) ?>" <?= (string)$value===(string)$option?'selected':'' ?>><?= e((string)$option) ?></option><?php endforeach;?></select>
        <?php else: ?>
          <input name="spec[<?= $id ?>]" value="<?= e((string)$value) ?>">
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="muted" id="no-spec-message">No specification definitions exist for this category yet.</p>
</section>
<?php endif; ?>

<label class="check"><input type="checkbox" name="active" value="1" <?= !isset($p['active'])||!empty($p['active'])?'checked':'' ?>> Active</label><div class="form-actions"><a class="secondary-button" href="<?= e(url('admin/products')) ?>">Cancel</a><?php if(!empty($p['slug'])):?><a class="secondary-button" href="<?= e(url('product/' . $p['slug'])) ?>" target="_blank" rel="noopener">Preview ↗</a><?php endif;?><button class="primary-button">Save product</button></div></form>
<script>
(function(){
  const category=document.getElementById('product-category');
  const fields=[...document.querySelectorAll('.spec-field')];
  const empty=document.getElementById('no-spec-message');
  if(!category||!fields.length)return;
  function update(){
    const selected=category.value; let visible=0;
    fields.forEach(field=>{const show=selected!==''&&field.dataset.category===selected;field.style.display=show?'flex':'none';field.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=!show);if(show)visible++;});
    if(empty)empty.style.display=visible?'none':'block';
  }
  category.addEventListener('change',update); update();
})();
</script>
