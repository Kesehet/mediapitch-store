<?php use MediaPitch\Core\Csrf; ?>
<section class="admin-grid two-col">
  <div class="panel form-panel">
    <h2>Upload image</h2>
    <form method="post" action="<?= e(url('admin/media/upload')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <label>Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label>
      <label>Alt text<input type="text" name="alt_text" maxlength="500" placeholder="Describe the image for accessibility"></label>
      <p class="muted">JPEG, PNG, WebP or GIF. Maximum 5 MB. A WebP thumbnail is generated automatically when GD is available.</p>
      <button class="primary-button">Upload image</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h2>Media library</h2><p>Search by filename or alt text. In-use images are protected from deletion.</p></div></div>
    <form method="get" action="<?= e(url('admin/media')) ?>" class="filter-row" style="margin-bottom:1rem">
      <input type="search" name="q" value="<?= e($query ?? '') ?>" placeholder="Search media…" aria-label="Search media">
      <button class="secondary-button">Search</button>
      <?php if(!empty($query)):?><a class="secondary-button" href="<?= e(url('admin/media')) ?>">Clear</a><?php endif;?>
    </form>
    <?php if (empty($items)): ?>
      <p class="muted"><?= !empty($query)?'No media matched your search.':'No uploads yet.' ?></p>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($items as $item): $displayPath=$item['thumbnail_path'] ?: $item['file_path']; $imageUrl=url(ltrim($item['file_path'],'/')); $usage=$item['usage']??[]; ?>
          <article class="media-card">
            <a href="<?= e($imageUrl) ?>" target="_blank" rel="noopener">
              <img src="<?= e(url(ltrim($displayPath,'/'))) ?>" alt="<?= e($item['alt_text'] ?: $item['original_name']) ?>" loading="lazy">
            </a>
            <div class="media-meta">
              <strong><?= e($item['original_name']) ?></strong>
              <small><?= (int)$item['width'] ?>×<?= (int)$item['height'] ?> · <?= e(round(((int)$item['file_size'])/1024) . ' KB') ?><?= !empty($item['optimized'])?' · thumbnail ready':'' ?></small>
              <input type="text" readonly value="<?= e($imageUrl) ?>" onclick="this.select()" aria-label="Image URL">
              <?php if (!empty($item['alt_text'])): ?><small>Alt: <?= e($item['alt_text']) ?></small><?php endif; ?>
              <?php if($usage):?><small><strong>In use:</strong> <?= e(implode(', ',$usage)) ?></small><?php else:?><small>Not currently used by CMS content.</small><?php endif;?>
              <?php if(!empty($categories)): ?>
                <form method="post" action="<?= e(url('admin/media/assign-category')) ?>" class="stack-form">
                  <?= Csrf::field() ?><input type="hidden" name="image_url" value="<?= e($imageUrl) ?>">
                  <label>Use as category image<select name="category_id" required><option value="">Choose category</option><?php foreach($categories as $category):?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach;?></select></label>
                  <button class="secondary-button">Set category image</button>
                </form>
              <?php endif; ?>
              <?php if(!$usage):?>
                <form method="post" action="<?= e(url('admin/media/delete')) ?>" onsubmit="return confirm('Delete this image permanently? This cannot be undone.')">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="link-button" style="color:#b91c1c">Delete image</button>
                </form>
              <?php endif;?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
