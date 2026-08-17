<?php use MediaPitch\Core\Csrf; ?>
<section class="admin-grid two-col">
  <div class="panel form-panel">
    <h2>Upload image</h2>
    <form method="post" action="<?= e(url('admin/media/upload')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <label>Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label>
      <label>Alt text<input type="text" name="alt_text" maxlength="500" placeholder="Describe the image for accessibility"></label>
      <p class="muted">JPEG, PNG, WebP or GIF. Maximum 5 MB.</p>
      <button class="primary-button">Upload image</button>
    </form>
  </div>

  <div class="panel">
    <h2>Media library</h2>
    <?php if (empty($items)): ?>
      <p class="muted">No uploads yet.</p>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($items as $item): ?>
          <article class="media-card">
            <a href="<?= e(url(ltrim($item['file_path'],'/'))) ?>" target="_blank" rel="noopener">
              <img src="<?= e(url(ltrim($item['file_path'],'/'))) ?>" alt="<?= e($item['alt_text'] ?: $item['original_name']) ?>" loading="lazy">
            </a>
            <div class="media-meta">
              <strong><?= e($item['original_name']) ?></strong>
              <small><?= (int)$item['width'] ?>×<?= (int)$item['height'] ?> · <?= e(round(((int)$item['file_size'])/1024) . ' KB') ?></small>
              <input type="text" readonly value="<?= e(url(ltrim($item['file_path'],'/'))) ?>" onclick="this.select()" aria-label="Image URL">
              <?php if (!empty($item['alt_text'])): ?><small>Alt: <?= e($item['alt_text']) ?></small><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
