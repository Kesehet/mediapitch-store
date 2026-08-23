<?php use MediaPitch\Core\Csrf; use MediaPitch\Services\ContentVisibility; $r=$review ?? []; ?>
<form method="post" action="<?= e(url('admin/reviews/save')) ?>" class="panel form-panel">
<?= Csrf::field() ?><?php if(!empty($r['id'])):?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif;?>
<div class="form-grid">
<label class="span-2">Title<input name="title" required value="<?= e($r['title'] ?? '') ?>"></label>
<label>Slug<input name="slug" required value="<?= e($r['slug'] ?? '') ?>"></label>
<label>Product<select name="product_id" required><option value="">Choose product</option><?php foreach($products as $p):?><option value="<?= (int)$p['id'] ?>" <?= (int)($r['product_id']??0)===(int)$p['id']?'selected':'' ?>><?= e($p['title']) ?></option><?php endforeach;?></select></label>
<label>Category<select name="category_id"><option value="">—</option><?php foreach($categories as $c):?><option value="<?= (int)$c['id'] ?>" <?= (int)($r['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach;?></select></label>
<label>Review score (0–10)<input type="number" min="0" max="10" step="0.1" name="score" value="<?= isset($r['score'])?e((string)$r['score']):'' ?>"></label>
<?php if(!empty($mediaItems)): ?><label class="span-2">Choose featured image<select id="review-media-picker"><option value="">— Select uploaded image —</option><?php foreach($mediaItems as $media): $mediaUrl=url(ltrim((string)$media['file_path'],'/')); ?><option value="<?= e($mediaUrl) ?>" <?= ($r['featured_image_url']??'')===$mediaUrl?'selected':'' ?>><?= e($media['original_name']) ?><?= !empty($media['alt_text'])?' — '.e($media['alt_text']):'' ?></option><?php endforeach;?></select></label><?php endif; ?>
<label class="span-2">Featured image URL<input type="url" id="review-image-url" name="featured_image_url" value="<?= e($r['featured_image_url'] ?? '') ?>"></label>
<label class="span-2">Summary<textarea name="excerpt" rows="4"><?= e($r['excerpt'] ?? '') ?></textarea></label>
<label class="span-2">Review body <small>HTML supported for now</small><textarea name="body" rows="16"><?= e($r['body'] ?? '') ?></textarea></label>
<label>Status<select name="status"><option value="draft" <?= ($r['status']??'draft')==='draft'?'selected':'' ?>>Draft</option><option value="scheduled" <?= ($r['status']??'')==='scheduled'?'selected':'' ?>>Scheduled</option><option value="published" <?= ($r['status']??'')==='published'?'selected':'' ?>>Published</option></select></label>
<label>Publish date <small><?= e(ContentVisibility::editorialTimezone()->getName()) ?></small><input type="datetime-local" name="published_at" value="<?= e(ContentVisibility::publishAtForInput($r['published_at'] ?? null)) ?>"></label>
<label class="span-2">SEO title<input name="seo_title" value="<?= e($r['seo_title'] ?? '') ?>"></label>
<label class="span-2">Meta description<textarea name="meta_description" rows="3"><?= e($r['meta_description'] ?? '') ?></textarea></label>
<label class="span-2">Canonical URL<input type="url" name="canonical_url" value="<?= e($r['canonical_url'] ?? '') ?>"></label>
</div>
<label class="check"><input type="checkbox" name="robots_index" value="1" <?= !isset($r['robots_index'])||!empty($r['robots_index'])?'checked':'' ?>> Allow search engines to index this review</label>
<div class="form-actions"><a class="secondary-button" href="<?= e(url('admin/reviews')) ?>">Cancel</a><?php if(!empty($r['slug'])):?><a class="secondary-button" href="<?= e(url('review/'.$r['slug'])) ?>" target="_blank" rel="noopener">Preview ↗</a><?php endif;?><button class="primary-button">Save review</button></div>
</form>
<script>const picker=document.getElementById('review-media-picker'),image=document.getElementById('review-image-url');if(picker&&image)picker.addEventListener('change',()=>{if(picker.value)image.value=picker.value;});</script>