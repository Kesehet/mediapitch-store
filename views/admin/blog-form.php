<?php use MediaPitch\Core\Csrf; $p=$post ?? []; ?>
<form method="post" action="<?= e(url('admin/blog/save')) ?>" class="panel form-panel">
  <?= Csrf::field() ?><?php if(!empty($p['id'])):?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?php endif; ?>
  <div class="form-grid">
    <label class="span-2">Title<input name="title" required value="<?= e($p['title'] ?? '') ?>"></label>
    <label>Slug<input name="slug" required value="<?= e($p['slug'] ?? '') ?>"></label>
    <label>Category<select name="category_id"><option value="">—</option><?php foreach($categories as $c):?><option value="<?= (int)$c['id'] ?>" <?= (int)($p['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach;?></select></label>
    <label class="span-2">Featured image URL<input type="url" name="featured_image_url" value="<?= e($p['featured_image_url'] ?? '') ?>"></label>
    <label class="span-2">Excerpt<textarea name="excerpt" rows="3"><?= e($p['excerpt'] ?? '') ?></textarea></label>
    <label class="span-2">Article body <small>HTML is supported for now</small><textarea name="body" rows="18"><?= e($p['body'] ?? '') ?></textarea></label>
    <label>Status<select name="status"><option value="draft" <?= ($p['status']??'draft')==='draft'?'selected':'' ?>>Draft</option><option value="scheduled" <?= ($p['status']??'')==='scheduled'?'selected':'' ?>>Scheduled</option><option value="published" <?= ($p['status']??'')==='published'?'selected':'' ?>>Published</option></select></label>
    <label>Publish date<input type="datetime-local" name="published_at" value="<?= !empty($p['published_at'])?e(date('Y-m-d\TH:i',strtotime((string)$p['published_at']))):'' ?>"></label>
    <label class="span-2">SEO title<input name="seo_title" value="<?= e($p['seo_title'] ?? '') ?>"></label>
    <label class="span-2">Meta description<textarea name="meta_description" rows="3"><?= e($p['meta_description'] ?? '') ?></textarea></label>
    <label class="span-2">Canonical URL<input type="url" name="canonical_url" value="<?= e($p['canonical_url'] ?? '') ?>"></label>
  </div>
  <label class="check"><input type="checkbox" name="robots_index" value="1" <?= !isset($p['robots_index'])||!empty($p['robots_index'])?'checked':'' ?>> Allow search engines to index this article</label>
  <div class="form-actions"><a class="secondary-button" href="<?= e(url('admin/blog')) ?>">Cancel</a><?php if(!empty($p['slug'])):?><a class="secondary-button" href="<?= e(url('blog/' . $p['slug'])) ?>" target="_blank" rel="noopener">Preview ↗</a><?php endif;?><button class="primary-button">Save article</button></div>
</form>
