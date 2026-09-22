<?php
use MediaPitch\Core\Csrf;
use MediaPitch\Repositories\AuditRepository;
use MediaPitch\Repositories\BrandRepository;
$p=$product ?? [];
$decode=function($v){$a=json_decode((string)$v,true);return is_array($a)?implode("\n",$a):'';};
$currentCategory=(int)($p['category_id'] ?? 0);
$brandRepo=new BrandRepository();
$brands=$brandRepo->activeOptions();
$currentBrandId=(int)($p['brand_id']??0);
if($currentBrandId>0 && !array_filter($brands,static fn($b)=>(int)$b['id']===$currentBrandId)){
    $currentBrand=$brandRepo->find($currentBrandId);
    if($currentBrand)$brands[]=['id'=>$currentBrandId,'name'=>$currentBrand['name'].' (archived)'];
}
$productHistory=!empty($p['id'])?(new AuditRepository())->forEntity('product',(int)$p['id'],20):[];
?>
<form method="post" action="<?= e(url('admin/products/save')) ?>" class="panel form-panel"><?= Csrf::field() ?><?php if(!empty($p['id'])):?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?php endif; ?>
<input type="hidden" id="metadata-provider" name="metadata_provider" value="">
<input type="hidden" id="metadata-marketplace" name="metadata_marketplace" value="">
<input type="hidden" id="detected-brand-name" name="detected_brand_name" value="">
<section class="spec-editor-block" style="margin-top:0">
<div class="panel-head"><div><h2>Import from product link</h2><p class="muted">Paste a product page and pull the useful details into this form.</p></div><span class="badge">Smart fill</span></div>
<div class="form-grid"><label class="span-2">Product page URL
<div style="display:flex;gap:8px;align-items:center"><input type="url" id="product-metadata-url" placeholder="https://www.amazon.in/dp/... or another retailer product page"><button type="button" class="secondary-button" id="fetch-product-metadata" style="white-space:nowrap">Fetch details</button></div>
<small>Amazon links use the configured Creators API after extracting the ASIN. Other product pages use JSON-LD/OpenGraph/meta tags. Existing values are never overwritten.</small>
</label></div>
<p class="muted" id="product-metadata-status" role="status" aria-live="polite" style="margin-bottom:0">You can also leave this box empty and the button will use the Amazon/Affiliate URL already entered below.</p>
</section>
<div class="form-grid"><label class="span-2">Product title<input id="product-title" name="title" required value="<?= e($p['title'] ?? '') ?>"></label><label class="span-2">Display title<input name="display_title" value="<?= e($p['display_title'] ?? '') ?>"></label><label>Slug <small>Generated from title if left blank</small><input id="product-slug" name="slug" value="<?= e($p['slug'] ?? '') ?>"></label><label>Source<select name="source" id="product-source"><option value="manual" <?= ($p['source']??'manual')==='manual'?'selected':'' ?>>Manual</option><option value="amazon_api" <?= ($p['source']??'')==='amazon_api'?'selected':'' ?>>Amazon API</option><option value="hybrid" <?= ($p['source']??'')==='hybrid'?'selected':'' ?>>API + manual overrides</option></select></label><label>Category<select name="category_id" id="product-category"><option value="">—</option><?php foreach($categories as $c):?><option value="<?= (int)$c['id'] ?>" <?= (int)($p['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach;?></select></label><label>Brand<select name="brand_id" id="product-brand"><option value="">—</option><?php foreach($brands as $b):?><option value="<?= (int)$b['id'] ?>" <?= $currentBrandId===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach;?></select></label><label>ASIN <small>10 letters/numbers; duplicates are blocked</small><input id="product-asin" name="asin" maxlength="10" pattern="[A-Za-z0-9]{10}" value="<?= e($p['asin'] ?? '') ?>"></label><label>Best-for label<input name="best_for_label" value="<?= e($p['best_for_label'] ?? '') ?>"></label><label>Price<input type="number" step="0.01" name="price" value="<?= e(isset($p['price'])?(string)$p['price']:'') ?>"></label><label>Previous price<input type="number" step="0.01" name="previous_price" value="<?= e(isset($p['previous_price'])?(string)$p['previous_price']:'') ?>"></label><label>Currency<input maxlength="3" name="currency" value="<?= e($p['currency'] ?? 'INR') ?>"></label><label>Score<input type="number" min="0" max="10" step="0.1" name="custom_score" value="<?= e(isset($p['custom_score'])?(string)$p['custom_score']:'') ?>"></label>
<?php if(!empty($mediaItems)): ?><label class="span-2">Choose main image from media library<select id="product-media-picker"><option value="">— Select uploaded image —</option><?php foreach($mediaItems as $media): $mediaUrl=url(ltrim((string)$media['file_path'],'/')); ?><option value="<?= e($mediaUrl) ?>" <?= ($p['main_image_url']??'')===$mediaUrl?'selected':'' ?>><?= e($media['original_name']) ?><?= !empty($media['alt_text'])?' — '.e($media['alt_text']):'' ?></option><?php endforeach;?></select><small><a href="<?= e(url('admin/media')) ?>" target="_blank" rel="noopener">Open media library ↗</a></small></label><?php endif; ?>
<label class="span-2">Main image URL<input type="url" id="main-image-url" name="main_image_url" value="<?= e($p['main_image_url'] ?? '') ?>"></label>
<?php if(!empty($mediaItems)): ?><label class="span-2">Add gallery image from media library<div style="display:flex;gap:8px"><select id="product-gallery-picker"><option value="">— Select uploaded image —</option><?php foreach($mediaItems as $media): $mediaUrl=url(ltrim((string)$media['file_path'],'/')); ?><option value="<?= e($mediaUrl) ?>"><?= e($media['original_name']) ?><?= !empty($media['alt_text'])?' — '.e($media['alt_text']):'' ?></option><?php endforeach;?></select><button type="button" class="secondary-button" id="add-gallery-image">Add</button></div></label><?php endif; ?>
<label class="span-2">Gallery image URLs <small>one per line, up to 20 images</small><textarea id="product-gallery" name="gallery" rows="5"><?= e($decode($p['gallery_json'] ?? null)) ?></textarea></label>
<label class="span-2">Amazon URL<input type="url" id="product-amazon-url" name="amazon_url" value="<?= e($p['amazon_url'] ?? '') ?>"></label><label class="span-2">Affiliate URL<input type="url" id="product-affiliate-url" name="affiliate_url" value="<?= e($p['affiliate_url'] ?? '') ?>"></label><label class="span-2">Short description<textarea name="short_description" rows="3"><?= e($p['short_description'] ?? '') ?></textarea></label><label class="span-2">Full description<textarea name="full_description" rows="6"><?= e($p['full_description'] ?? '') ?></textarea></label><label>Features <small>one per line</small><textarea name="features" rows="6"><?= e($decode($p['features_json'] ?? null)) ?></textarea></label><label>Pros <small>one per line</small><textarea name="pros" rows="6"><?= e($decode($p['pros_json'] ?? null)) ?></textarea></label><label>Cons <small>one per line</small><textarea name="cons" rows="6"><?= e($decode($p['cons_json'] ?? null)) ?></textarea></label><label>Editorial notes<textarea name="editorial_notes" rows="6"><?= e($p['editorial_notes'] ?? '') ?></textarea></label></div>

<?php if (!empty($specDefinitions)): ?>
<section class="spec-editor-block"><div class="panel-head"><div><h2>Category specifications</h2><p class="muted">Fields change automatically when you select a category.</p></div><a href="<?= e(url('admin/specifications')) ?>">Manage definitions</a></div><div class="form-grid" id="product-spec-fields">
<?php foreach ($specDefinitions as $definition): if(isset($definition['active']) && !(bool)$definition['active']) continue;$id=(int)$definition['id'];$stored=$specValues[$id] ?? [];$value=$stored['value_text'] ?? ($stored['value_number'] ?? ($stored['value_boolean'] ?? ''));$options=json_decode((string)($definition['options_json'] ?? ''),true);if(!is_array($options))$options=[]; ?>
<label class="spec-field" data-category="<?= (int)$definition['category_id'] ?>"><?= e($definition['name']) ?><?php if(!empty($definition['unit'])): ?> <small>(<?= e($definition['unit']) ?>)</small><?php endif; ?><?php if ($definition['data_type']==='number'): ?><input type="number" step="any" name="spec[<?= $id ?>]" value="<?= e((string)$value) ?>"><?php elseif ($definition['data_type']==='boolean'): ?><select name="spec[<?= $id ?>]"><option value="">—</option><option value="1" <?= (string)$value==='1'?'selected':'' ?>>Yes</option><option value="0" <?= (string)$value==='0'?'selected':'' ?>>No</option></select><?php elseif ($definition['data_type']==='select'): ?><select name="spec[<?= $id ?>]"><option value="">—</option><?php foreach($options as $option):?><option value="<?= e((string)$option) ?>" <?= (string)$value===(string)$option?'selected':'' ?>><?= e((string)$option) ?></option><?php endforeach;?></select><?php else: ?><input name="spec[<?= $id ?>]" value="<?= e((string)$value) ?>"><?php endif; ?></label>
<?php endforeach; ?></div><p class="muted" id="no-spec-message">No active specification definitions exist for this category yet.</p></section>
<?php endif; ?>

<label class="check"><input type="checkbox" id="product-active" name="active" value="1" <?= !isset($p['active'])||!empty($p['active'])?'checked':'' ?>> Active</label><div class="form-actions"><a class="secondary-button" href="<?= e(url('admin/products')) ?>">Cancel</a><?php if(!empty($p['slug'])):?><a class="secondary-button" href="<?= e(url('product/' . $p['slug'])) ?>" target="_blank" rel="noopener">Preview ↗</a><?php endif;?><button class="primary-button">Save product</button></div></form>

<?php if(!empty($p['id'])): ?>
<section class="panel" style="margin-top:1rem"><div class="panel-head"><div><h2>Change history</h2><p>Recent audited changes for this product.</p></div><?php if(\MediaPitch\Core\Auth::isAdministrator()):?><a href="<?= e(url('admin/audit')) ?>">Full audit log</a><?php endif;?></div>
<?php if(!$productHistory):?><p class="empty">No audited changes recorded yet.</p><?php else:?><div class="table-wrap"><table class="data-table"><thead><tr><th>When</th><th>Action</th><th>User</th><th>Summary</th></tr></thead><tbody><?php foreach($productHistory as $event):?><tr><td><?= e((string)$event['created_at']) ?></td><td><code><?= e((string)$event['action']) ?></code></td><td><?= e((string)($event['user_name']??$event['user_email']??'System')) ?></td><td><?= e((string)($event['summary']??'')) ?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
<?php endif; ?>

<script>
(function(){const category=document.getElementById('product-category');const fields=[...document.querySelectorAll('.spec-field')];const empty=document.getElementById('no-spec-message');if(category&&fields.length){function update(){const selected=category.value;let visible=0;fields.forEach(field=>{const show=selected!==''&&field.dataset.category===selected;field.style.display=show?'flex':'none';field.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=!show);if(show)visible++;});if(empty)empty.style.display=visible?'none':'block';}category.addEventListener('change',update);update();}else if(empty){empty.style.display='block';}const picker=document.getElementById('product-media-picker');const image=document.getElementById('main-image-url');if(picker&&image)picker.addEventListener('change',()=>{if(picker.value)image.value=picker.value;});const galleryPicker=document.getElementById('product-gallery-picker');const gallery=document.getElementById('product-gallery');const addGallery=document.getElementById('add-gallery-image');if(galleryPicker&&gallery&&addGallery)addGallery.addEventListener('click',()=>{if(!galleryPicker.value)return;const lines=gallery.value.split(/\r?\n/).map(v=>v.trim()).filter(Boolean);if(!lines.includes(galleryPicker.value))lines.push(galleryPicker.value);gallery.value=lines.join('\n');galleryPicker.value='';});const title=document.getElementById('product-title');const slug=document.getElementById('product-slug');if(title&&slug){let slugManuallyEdited=slug.value.trim()!=='';const slugify=value=>value.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');slug.addEventListener('input',()=>{slugManuallyEdited=slug.value.trim()!=='';});title.addEventListener('input',()=>{if(!slugManuallyEdited)slug.value=slugify(title.value);});if(!slug.value.trim()&&title.value.trim())slug.value=slugify(title.value);}

const metadataButton=document.getElementById('fetch-product-metadata');
const metadataUrl=document.getElementById('product-metadata-url');
const metadataStatus=document.getElementById('product-metadata-status');
const amazonUrl=document.getElementById('product-amazon-url');
const affiliateUrl=document.getElementById('product-affiliate-url');
const brandSelect=document.getElementById('product-brand');
const detectedBrand=document.getElementById('detected-brand-name');
const metadataProvider=document.getElementById('metadata-provider');
const metadataMarketplace=document.getElementById('metadata-marketplace');
const sourceSelect=document.getElementById('product-source');
const activeInput=document.getElementById('product-active');
const productId=form.querySelector('input[name="id"]');
const endpoint=<?= json_encode(url('admin/products/fetch-metadata'),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const csrf=form.querySelector('input[name="_csrf"]');

function fillIfEmpty(name,value){
    if(value===null||value===undefined||value==='')return false;
    const field=form.querySelector('[name="'+name+'"]');
    if(!field||String(field.value||'').trim()!=='')return false;
    field.value=Array.isArray(value)?value.join('\n'):String(value);
    field.dispatchEvent(new Event('input',{bubbles:true}));
    field.dispatchEvent(new Event('change',{bubbles:true}));
    return true;
}
function selectBrand(name){
    if(!brandSelect||!name||brandSelect.value)return false;
    const wanted=String(name).trim().toLowerCase();
    const option=[...brandSelect.options].find(item=>item.value&&item.textContent.trim().replace(/\s+\(archived\)$/i,'').toLowerCase()===wanted);
    if(option){brandSelect.value=option.value;brandSelect.dispatchEvent(new Event('change',{bubbles:true}));if(detectedBrand)detectedBrand.value='';return true;}
    if(detectedBrand)detectedBrand.value=String(name).trim();
    return false;
}
if(metadataButton&&metadataUrl&&metadataStatus&&csrf){
    metadataButton.addEventListener('click',async()=>{
        const target=metadataUrl.value.trim()||(amazonUrl?amazonUrl.value.trim():'')||(affiliateUrl?affiliateUrl.value.trim():'');
        if(!target){metadataStatus.textContent='Paste a product URL first.';metadataUrl.focus();return;}
        metadataButton.disabled=true;
        const originalText=metadataButton.textContent;
        metadataButton.textContent='Fetching…';
        metadataStatus.textContent='Reading product metadata…';
        try{
            const body=new FormData();
            body.append('_csrf',csrf.value);
            body.append('url',target);
            const response=await fetch(endpoint,{method:'POST',body,headers:{'Accept':'application/json'}});
            let payload=null;
            try{payload=await response.json();}catch(error){}
            if(!response.ok||!payload||!payload.ok)throw new Error(payload&&payload.error?payload.error:'Could not read metadata from that page.');
            const data=payload.metadata||{};
            let filled=0;
            if(fillIfEmpty('title',data.title))filled++;
            if(fillIfEmpty('asin',data.asin))filled++;
            if(fillIfEmpty('main_image_url',data.main_image_url))filled++;
            if(fillIfEmpty('short_description',data.short_description))filled++;
            if(fillIfEmpty('features',data.features))filled++;
            if(fillIfEmpty('price',data.price))filled++;
            if(fillIfEmpty('currency',data.currency))filled++;
            if(fillIfEmpty('amazon_url',data.amazon_url))filled++;
            if(fillIfEmpty('affiliate_url',data.affiliate_url))filled++;
            const matchedBrand=selectBrand(data.brand||'');
            if(matchedBrand)filled++;
            if(metadataProvider)metadataProvider.value=data.provider||'';
            if(metadataMarketplace)metadataMarketplace.value=data.marketplace||'';
            if(data.provider==='amazon_creators_api'&&!productId){
                if(sourceSelect&&sourceSelect.value==='manual')sourceSelect.value='amazon_api';
                if(activeInput)activeInput.checked=false;
            }
            const brandNote=data.brand&&!matchedBrand&&!brandSelect.value?' Brand “'+data.brand+'” will be created when you save.':'';
            const warning=data.warning?' '+data.warning:'';
            metadataStatus.textContent=(filled?'Filled '+filled+' field'+(filled===1?'':'s')+'.':'Metadata checked; existing form values were preserved.')+brandNote+warning;
        }catch(error){
            metadataStatus.textContent=error instanceof Error?error.message:'Could not fetch product metadata.';
        }finally{
            metadataButton.disabled=false;
            metadataButton.textContent=originalText;
        }
    });
}
})();
</script>