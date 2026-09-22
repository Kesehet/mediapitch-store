<?php
use MediaPitch\Core\Csrf;
use MediaPitch\Services\ContentVisibility;

$g=$guide ?? [];
$rows=$g['products'] ?? [];
if(!$rows) $rows=[[]];
$guideId=!empty($g['id']) ? (int)$g['id'] : null;
?>
<form method="post"
      action="<?= e(url('admin/guides/save')) ?>"
      class="panel form-panel"
      id="guide-form"
      data-guide-id="<?= $guideId ?: 'new' ?>">
    <?= Csrf::field() ?>
    <?php if($guideId): ?><input type="hidden" name="id" value="<?= $guideId ?>"><?php endif; ?>

    <div class="form-grid">
        <label class="span-2">Title<input name="title" required value="<?= e($g['title'] ?? '') ?>"></label>
        <label>Slug<input name="slug" required value="<?= e($g['slug'] ?? '') ?>"></label>
        <label>Category
            <select name="category_id">
                <option value="">—</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($g['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="span-2">Tags <small>comma-separated; up to 20</small><input name="tags" maxlength="1000" placeholder="air purifiers, HEPA, home appliances" value="<?= e($g['tags'] ?? '') ?>"></label>
        <label>Status
            <select name="status">
                <option value="draft" <?= ($g['status']??'draft')==='draft'?'selected':'' ?>>Draft</option>
                <option value="scheduled" <?= ($g['status']??'')==='scheduled'?'selected':'' ?>>Scheduled</option>
                <option value="published" <?= ($g['status']??'')==='published'?'selected':'' ?>>Published</option>
            </select>
        </label>
        <label>Publish date <small><?= e(ContentVisibility::editorialTimezone()->getName()) ?></small><input type="datetime-local" name="published_at" value="<?= e(ContentVisibility::publishAtForInput($g['published_at'] ?? null)) ?>"></label>
        <label class="span-2">Excerpt<textarea name="excerpt" rows="3"><?= e($g['excerpt'] ?? '') ?></textarea></label>
        <label class="span-2">Body<textarea name="body" rows="10"><?= e($g['body'] ?? '') ?></textarea></label>

        <?php if(!empty($mediaItems)): ?>
            <label class="span-2">Choose featured image
                <select id="guide-media-picker">
                    <option value="">— Select uploaded image —</option>
                    <?php foreach($mediaItems as $media):
                        $mediaUrl=url(ltrim((string)$media['file_path'],'/')); ?>
                        <option value="<?= e($mediaUrl) ?>" <?= ($g['featured_image_url']??'')===$mediaUrl?'selected':'' ?>>
                            <?= e($media['original_name']) ?><?= !empty($media['alt_text'])?' — '.e($media['alt_text']):'' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <label class="span-2">Featured image URL<input type="url" id="guide-image-url" name="featured_image_url" value="<?= e($g['featured_image_url'] ?? '') ?>"></label>
        <label>SEO title<input name="seo_title" value="<?= e($g['seo_title'] ?? '') ?>"></label>
        <label>Meta description<textarea name="meta_description" rows="3"><?= e($g['meta_description'] ?? '') ?></textarea></label>
        <label class="span-2">Canonical URL<input type="url" name="canonical_url" value="<?= e($g['canonical_url'] ?? '') ?>"></label>
    </div>
    <label class="check"><input type="checkbox" name="robots_index" value="1" <?= !isset($g['robots_index'])||!empty($g['robots_index'])?'checked':'' ?>> Allow search engines to index this buying guide</label>

    <div class="panel-head subhead">
        <div>
            <h2>Ranked products</h2>
            <p>Choose an existing product or type a new product name. New names are saved as incomplete products so you can finish their catalogue data later.</p>
        </div>
        <button type="button" class="secondary-button" id="add-product">+ Add product</button>
    </div>

    <datalist id="guide-product-list">
        <?php foreach($productOptions as $p): ?>
            <option value="<?= e($p['title'].' · #'.(int)$p['id']) ?>"
                    data-id="<?= (int)$p['id'] ?>"
                    label="<?= !empty($p['active']) ? 'Existing product' : 'Incomplete product' ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <div id="product-rows">
        <?php foreach($rows as $i=>$r):
            $selectedId=(int)($r['product_id']??0);
            $selectedTitle=trim((string)($r['product_title']??''));
            $pickerValue=$selectedTitle;
            if($selectedId && !preg_match('/\s*·\s*#'.preg_quote((string)$selectedId,'/').'\s*$/u',$pickerValue)){
                $pickerValue=trim($selectedTitle).' · #'.$selectedId;
            }
        ?>
            <div class="product-row guide-product-row" draggable="true">
                <button type="button" class="drag-handle" title="Drag to reorder" aria-label="Drag product row">↕</button>
                <label>Rank<input class="rank-input" type="number" min="1" name="rank_position[]" value="<?= e(isset($r['rank_position'])?(string)$r['rank_position']:(string)($i+1)) ?>"></label>
                <label class="grow">Product
                    <input class="product-picker-input"
                           name="product_title[]"
                           list="guide-product-list"
                           autocomplete="off"
                           placeholder="Choose or type a product…"
                           value="<?= e($pickerValue) ?>">
                    <input class="product-id-input" type="hidden" name="product_id[]" value="<?= $selectedId ?: '' ?>">
                </label>
                <label>Score<input type="number" min="0" max="10" step="0.1" name="score[]" value="<?= e(isset($r['score'])?(string)$r['score']:'') ?>"></label>
                <label>Best for<input name="product_best_for[]" value="<?= e($r['best_for_label'] ?? '') ?>"></label>
                <label class="wide">Recommendation<textarea name="recommendation[]" rows="2"><?= e($r['recommendation'] ?? '') ?></textarea></label>
                <label>CTA text<input name="cta_text[]" value="<?= e($r['cta_text'] ?? 'Check Price on Amazon') ?>"></label>
                <button type="button" class="remove-row">Remove</button>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="guide-product-warning" class="flash error" style="display:none"></div>
    <div class="form-actions">
        <a class="secondary-button" href="<?= e(url('admin/guides')) ?>">Cancel</a>
        <button class="primary-button">Save guide</button>
    </div>
</form>

<template id="product-template">
    <div class="product-row guide-product-row" draggable="true">
        <button type="button" class="drag-handle" title="Drag to reorder" aria-label="Drag product row">↕</button>
        <label>Rank<input class="rank-input" type="number" min="1" name="rank_position[]"></label>
        <label class="grow">Product
            <input class="product-picker-input" name="product_title[]" list="guide-product-list" autocomplete="off" placeholder="Choose or type a product…">
            <input class="product-id-input" type="hidden" name="product_id[]">
        </label>
        <label>Score<input type="number" min="0" max="10" step="0.1" name="score[]"></label>
        <label>Best for<input name="product_best_for[]"></label>
        <label class="wide">Recommendation<textarea name="recommendation[]" rows="2"></textarea></label>
        <label>CTA text<input name="cta_text[]" value="Check Price on Amazon"></label>
        <button type="button" class="remove-row">Remove</button>
    </div>
</template>

<script>
(function(){
    const rows=document.getElementById('product-rows');
    const tpl=document.getElementById('product-template');
    const form=document.getElementById('guide-form');
    const warning=document.getElementById('guide-product-warning');
    const options=[...document.querySelectorAll('#guide-product-list option')];
    const optionMap=new Map(options.map(o=>[o.value,String(o.dataset.id||'')]));
    const draftKey='mediapitch:buying-guide-draft:v1:'+(form.dataset.guideId||'new');
    const serverSaved=<?= !empty($success) ? 'true' : 'false' ?>;
    let draftTimer=null;

    function syncPicker(input){
        const row=input.closest('.guide-product-row');
        const hidden=row.querySelector('.product-id-input');
        hidden.value=optionMap.get(input.value)||'';
    }

    function renumber(){
        [...rows.querySelectorAll('.guide-product-row')].forEach((row,i)=>{
            row.querySelector('.rank-input').value=i+1;
        });
    }

    function bindRow(row){
        const picker=row.querySelector('.product-picker-input');
        picker.addEventListener('input',()=>syncPicker(picker));
        picker.addEventListener('change',()=>syncPicker(picker));
    }

    function addRow(values={}){
        const fragment=tpl.content.cloneNode(true);
        const row=fragment.querySelector('.guide-product-row');
        rows.append(fragment);
        bindRow(row);

        row.querySelector('.product-picker-input').value=values.product_title||'';
        row.querySelector('.product-id-input').value=values.product_id||'';
        row.querySelector('.rank-input').value=values.rank_position||'';
        row.querySelector('[name="score[]"]').value=values.score||'';
        row.querySelector('[name="product_best_for[]"]').value=values.product_best_for||'';
        row.querySelector('[name="recommendation[]"]').value=values.recommendation||'';
        row.querySelector('[name="cta_text[]"]').value=values.cta_text||'Check Price on Amazon';
        return row;
    }

    function captureDraft(){
        const fields={};
        [
            'title','slug','category_id','status','published_at','excerpt','body',
            'featured_image_url','seo_title','meta_description','tags','canonical_url'
        ].forEach(name=>{
            const control=form.elements.namedItem(name);
            if(control) fields[name]=control.value;
        });

        const products=[...rows.querySelectorAll('.guide-product-row')].map(row=>({
            product_title:row.querySelector('.product-picker-input').value,
            product_id:row.querySelector('.product-id-input').value,
            rank_position:row.querySelector('.rank-input').value,
            score:row.querySelector('[name="score[]"]').value,
            product_best_for:row.querySelector('[name="product_best_for[]"]').value,
            recommendation:row.querySelector('[name="recommendation[]"]').value,
            cta_text:row.querySelector('[name="cta_text[]"]').value
        }));

        return {version:1,savedAt:Date.now(),fields,products};
    }

    function draftSignature(draft){
        if(!draft) return '';
        return JSON.stringify({fields:draft.fields||{},products:draft.products||[]});
    }

    function saveDraftNow(){
        try{
            const draft=captureDraft();
            localStorage.setItem(draftKey,JSON.stringify(draft));
        }catch(_){}
    }

    function scheduleDraftSave(){
        clearTimeout(draftTimer);
        draftTimer=setTimeout(saveDraftNow,500);
    }

    function restoreDraft(draft){
        if(!draft || !draft.fields) return;
        Object.entries(draft.fields).forEach(([name,value])=>{
            const control=form.elements.namedItem(name);
            if(control) control.value=value ?? '';
        });

        rows.innerHTML='';
        const products=Array.isArray(draft.products) && draft.products.length ? draft.products : [{}];
        products.forEach(product=>addRow(product));
        renumber();
        scheduleDraftSave();
    }

    function showDraftRecovery(){
        let stored=null;
        try{
            stored=JSON.parse(localStorage.getItem(draftKey)||'null');
        }catch(_){}

        if(!stored || draftSignature(stored)===draftSignature(captureDraft())) return;

        const banner=document.createElement('div');
        banner.className='flash';
        banner.style.marginBottom='1rem';

        const savedLabel=stored.savedAt ? new Date(stored.savedAt).toLocaleString() : 'an earlier session';
        banner.innerHTML='<strong>Unsaved local draft found.</strong> A browser copy from '+savedLabel+' can be restored. '+
            '<button type="button" class="secondary-button" data-restore-guide-draft>Restore draft</button> '+
            '<button type="button" class="secondary-button" data-dismiss-guide-draft>Dismiss</button>';

        form.before(banner);
        banner.querySelector('[data-restore-guide-draft]').addEventListener('click',()=>{
            restoreDraft(stored);
            banner.remove();
        });
        banner.querySelector('[data-dismiss-guide-draft]').addEventListener('click',()=>{
            try{localStorage.removeItem(draftKey);}catch(_){}
            banner.remove();
        });
    }

    [...rows.querySelectorAll('.guide-product-row')].forEach(bindRow);

    document.getElementById('add-product').addEventListener('click',()=>{
        const row=addRow();
        renumber();
        row.querySelector('.product-picker-input').focus();
        scheduleDraftSave();
    });

    rows.addEventListener('click',e=>{
        if(e.target.classList.contains('remove-row') && rows.children.length>1){
            e.target.closest('.guide-product-row').remove();
            renumber();
            scheduleDraftSave();
        }
    });

    let dragged=null;
    rows.addEventListener('dragstart',e=>{
        const row=e.target.closest('.guide-product-row');
        if(!row) return;
        dragged=row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed='move';
    });
    rows.addEventListener('dragend',()=>{
        if(dragged) dragged.classList.remove('dragging');
        dragged=null;
        renumber();
        scheduleDraftSave();
    });
    rows.addEventListener('dragover',e=>{
        e.preventDefault();
        if(!dragged) return;
        const target=e.target.closest('.guide-product-row');
        if(!target || target===dragged) return;
        const rect=target.getBoundingClientRect();
        rows.insertBefore(dragged,e.clientY<rect.top+rect.height/2 ? target : target.nextSibling);
    });

    form.addEventListener('input',scheduleDraftSave);
    form.addEventListener('change',scheduleDraftSave);
    window.addEventListener('beforeunload',saveDraftNow);

    form.addEventListener('submit',()=>{
        rows.querySelectorAll('.guide-product-row').forEach(row=>syncPicker(row.querySelector('.product-picker-input')));
        saveDraftNow();
        warning.style.display='none';
    });

    const mediaPicker=document.getElementById('guide-media-picker');
    const image=document.getElementById('guide-image-url');
    if(mediaPicker && image){
        mediaPicker.addEventListener('change',()=>{
            if(mediaPicker.value){
                image.value=mediaPicker.value;
                scheduleDraftSave();
            }
        });
    }

    if(serverSaved){
        try{
            localStorage.removeItem(draftKey);
            localStorage.removeItem('mediapitch:buying-guide-draft:v1:new');
        }catch(_){}
    }else{
        showDraftRecovery();
    }

    renumber();
})();
</script>
