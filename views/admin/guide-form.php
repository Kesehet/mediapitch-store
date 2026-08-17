<?php use MediaPitch\Core\Csrf; $g=$guide ?? []; $rows=$g['products'] ?? []; if(!$rows)$rows=[[]]; ?>
<form method="post" action="<?= e(url('admin/guides/save')) ?>" class="panel form-panel" id="guide-form"><?= Csrf::field() ?><?php if(!empty($g['id'])):?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><?php endif;?>
<div class="form-grid"><label class="span-2">Title<input name="title" required value="<?= e($g['title'] ?? '') ?>"></label><label>Slug<input name="slug" required value="<?= e($g['slug'] ?? '') ?>"></label><label>Category<select name="category_id"><option value="">—</option><?php foreach($categories as $c):?><option value="<?= (int)$c['id'] ?>" <?= (int)($g['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach;?></select></label><label>Status<select name="status"><option value="draft" <?= ($g['status']??'draft')==='draft'?'selected':'' ?>>Draft</option><option value="scheduled" <?= ($g['status']??'')==='scheduled'?'selected':'' ?>>Scheduled</option><option value="published" <?= ($g['status']??'')==='published'?'selected':'' ?>>Published</option></select></label><label>Publish date<input type="datetime-local" name="published_at" value="<?= !empty($g['published_at'])?e(date('Y-m-d\TH:i',strtotime($g['published_at']))):'' ?>"></label><label class="span-2">Excerpt<textarea name="excerpt" rows="3"><?= e($g['excerpt'] ?? '') ?></textarea></label><label class="span-2">Body<textarea name="body" rows="10"><?= e($g['body'] ?? '') ?></textarea></label>
<?php if(!empty($mediaItems)): ?><label class="span-2">Choose featured image<select id="guide-media-picker"><option value="">— Select uploaded image —</option><?php foreach($mediaItems as $media): $mediaUrl=url(ltrim((string)$media['file_path'],'/')); ?><option value="<?= e($mediaUrl) ?>" <?= ($g['featured_image_url']??'')===$mediaUrl?'selected':'' ?>><?= e($media['original_name']) ?><?= !empty($media['alt_text'])?' — '.e($media['alt_text']):'' ?></option><?php endforeach;?></select></label><?php endif; ?>
<label class="span-2">Featured image URL<input type="url" id="guide-image-url" name="featured_image_url" value="<?= e($g['featured_image_url'] ?? '') ?>"></label><label>SEO title<input name="seo_title" value="<?= e($g['seo_title'] ?? '') ?>"></label><label>Meta description<textarea name="meta_description" rows="3"><?= e($g['meta_description'] ?? '') ?></textarea></label></div>
<div class="panel-head subhead"><div><h2>Ranked products</h2><p>Add products, search by title, and drag rows to reorder. Rank numbers update automatically.</p></div><button type="button" class="secondary-button" id="add-product">+ Add product</button></div>
<datalist id="guide-product-list"><?php foreach($productOptions as $p):?><option value="<?= e($p['title'].' · #'.(int)$p['id']) ?>" data-id="<?= (int)$p['id'] ?>"></option><?php endforeach;?></datalist>
<div id="product-rows">
<?php foreach($rows as $i=>$r): $selectedId=(int)($r['product_id']??0); $selectedTitle=(string)($r['product_title']??''); $pickerValue=$selectedId?($selectedTitle.' · #'.$selectedId):''; ?>
<div class="product-row guide-product-row" draggable="true">
  <button type="button" class="drag-handle" title="Drag to reorder" aria-label="Drag product row">↕</button>
  <label>Rank<input class="rank-input" type="number" min="1" name="rank_position[]" value="<?= e(isset($r['rank_position'])?(string)$r['rank_position']:(string)($i+1)) ?>"></label>
  <label class="grow">Product<input class="product-picker-input" list="guide-product-list" autocomplete="off" placeholder="Start typing a product…" value="<?= e($pickerValue) ?>"><input class="product-id-input" type="hidden" name="product_id[]" value="<?= $selectedId ?: '' ?>"></label>
  <label>Score<input type="number" min="0" max="10" step="0.1" name="score[]" value="<?= e(isset($r['score'])?(string)$r['score']:'') ?>"></label>
  <label>Best for<input name="product_best_for[]" value="<?= e($r['best_for_label'] ?? '') ?>"></label>
  <label class="wide">Recommendation<textarea name="recommendation[]" rows="2"><?= e($r['recommendation'] ?? '') ?></textarea></label>
  <label>CTA text<input name="cta_text[]" value="<?= e($r['cta_text'] ?? 'Check Price on Amazon') ?>"></label>
  <button type="button" class="remove-row">Remove</button>
</div>
<?php endforeach;?></div>
<div id="guide-product-warning" class="flash error" style="display:none"></div>
<div class="form-actions"><a class="secondary-button" href="<?= e(url('admin/guides')) ?>">Cancel</a><button class="primary-button">Save guide</button></div></form>
<template id="product-template"><div class="product-row guide-product-row" draggable="true"><button type="button" class="drag-handle" title="Drag to reorder" aria-label="Drag product row">↕</button><label>Rank<input class="rank-input" type="number" min="1" name="rank_position[]"></label><label class="grow">Product<input class="product-picker-input" list="guide-product-list" autocomplete="off" placeholder="Start typing a product…"><input class="product-id-input" type="hidden" name="product_id[]"></label><label>Score<input type="number" min="0" max="10" step="0.1" name="score[]"></label><label>Best for<input name="product_best_for[]"></label><label class="wide">Recommendation<textarea name="recommendation[]" rows="2"></textarea></label><label>CTA text<input name="cta_text[]" value="Check Price on Amazon"></label><button type="button" class="remove-row">Remove</button></div></template>
<script>
(function(){
const rows=document.getElementById('product-rows'),tpl=document.getElementById('product-template'),form=document.getElementById('guide-form'),warning=document.getElementById('guide-product-warning');
const options=[...document.querySelectorAll('#guide-product-list option')];
const optionMap=new Map(options.map(o=>[o.value,String(o.dataset.id||'')]));
function syncPicker(input){const row=input.closest('.guide-product-row'),hidden=row.querySelector('.product-id-input');hidden.value=optionMap.get(input.value)||'';}
function renumber(){[...rows.querySelectorAll('.guide-product-row')].forEach((row,i)=>{row.querySelector('.rank-input').value=i+1;});}
function bindRow(row){const picker=row.querySelector('.product-picker-input');picker.addEventListener('input',()=>syncPicker(picker));picker.addEventListener('change',()=>syncPicker(picker));}
[...rows.querySelectorAll('.guide-product-row')].forEach(bindRow);
document.getElementById('add-product').addEventListener('click',()=>{const fragment=tpl.content.cloneNode(true);const row=fragment.querySelector('.guide-product-row');rows.append(fragment);bindRow(row);renumber();row.querySelector('.product-picker-input').focus();});
rows.addEventListener('click',e=>{if(e.target.classList.contains('remove-row')&&rows.children.length>1){e.target.closest('.guide-product-row').remove();renumber();}});
let dragged=null;
rows.addEventListener('dragstart',e=>{const row=e.target.closest('.guide-product-row');if(!row)return;dragged=row;row.classList.add('dragging');e.dataTransfer.effectAllowed='move';});
rows.addEventListener('dragend',()=>{if(dragged)dragged.classList.remove('dragging');dragged=null;renumber();});
rows.addEventListener('dragover',e=>{e.preventDefault();if(!dragged)return;const target=e.target.closest('.guide-product-row');if(!target||target===dragged)return;const rect=target.getBoundingClientRect();rows.insertBefore(dragged,e.clientY<rect.top+rect.height/2?target:target.nextSibling);});
form.addEventListener('submit',e=>{
  const ids=[];let invalid=false;
  rows.querySelectorAll('.guide-product-row').forEach(row=>{const picker=row.querySelector('.product-picker-input'),hidden=row.querySelector('.product-id-input');syncPicker(picker);if(picker.value.trim()&&!hidden.value)invalid=true;if(hidden.value)ids.push(hidden.value);});
  const duplicates=ids.filter((id,i)=>ids.indexOf(id)!==i);
  if(invalid||duplicates.length){e.preventDefault();warning.style.display='block';warning.textContent=invalid?'Choose products from the suggestion list so they can be matched correctly.':'The same product is selected more than once. Remove the duplicate before saving.';warning.scrollIntoView({behavior:'smooth',block:'center'});}
});
const mediaPicker=document.getElementById('guide-media-picker'),image=document.getElementById('guide-image-url');if(mediaPicker&&image)mediaPicker.addEventListener('change',()=>{if(mediaPicker.value)image.value=mediaPicker.value;});
renumber();
})();
</script>