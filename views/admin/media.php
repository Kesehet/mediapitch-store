<?php
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;

$total=count($items ?? []);
$inUse=0;$missingAlt=0;$totalBytes=0;
foreach(($items ?? []) as $mediaItem){
    if(!empty($mediaItem['usage'])) $inUse++;
    if(trim((string)($mediaItem['alt_text'] ?? ''))==='') $missingAlt++;
    $totalBytes+=(int)($mediaItem['file_size'] ?? 0);
}
$unused=max(0,$total-$inUse);
$canManageProducts=Auth::canManageProducts();
$formatBytes=static function(int $bytes): string {
    if($bytes>=1048576) return number_format($bytes/1048576,1).' MB';
    return max(1,(int)round($bytes/1024)).' KB';
};
?>
<section class="media-page" data-media-library>
  <div class="media-hero">
    <div>
      <span class="media-eyebrow">Asset workspace</span>
      <h2>Media Library</h2>
      <p>Upload, find and reuse images without digging through filenames. Usage protection keeps linked assets safe.</p>
    </div>
    <button class="primary-button media-upload-jump" type="button" data-upload-toggle>+ Upload images</button>
  </div>

  <div class="media-stats" aria-label="Media library summary">
    <div class="media-stat"><span>Total assets</span><strong><?= $total ?></strong><small><?= e($formatBytes($totalBytes)) ?> stored</small></div>
    <div class="media-stat"><span>In use</span><strong><?= $inUse ?></strong><small>Protected from deletion</small></div>
    <div class="media-stat"><span>Unused</span><strong><?= $unused ?></strong><small>Available for cleanup</small></div>
    <div class="media-stat <?= $missingAlt>0?'needs-attention':'' ?>"><span>Missing alt text</span><strong><?= $missingAlt ?></strong><small><?= $missingAlt>0?'Worth fixing for accessibility':'All covered' ?></small></div>
  </div>

  <div class="media-upload-panel" data-upload-panel>
    <div class="media-upload-copy">
      <span class="media-upload-icon" aria-hidden="true">↥</span>
      <div><h3>Upload images</h3><p>Drop several images here or browse. JPEG, PNG, WebP and GIF up to 5 MB each.</p></div>
    </div>
    <form method="post" action="<?= e(url('admin/media/upload')) ?>" enctype="multipart/form-data" class="media-upload-form" data-upload-form>
      <?= Csrf::field() ?>
      <label class="media-dropzone" data-dropzone>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required data-media-files>
        <span class="media-dropzone-title">Drop images here</span>
        <span>or <strong>browse files</strong> from your computer</span>
      </label>
      <div class="media-upload-preview" data-upload-preview hidden></div>
      <label class="media-upload-fallback">Default alt text <span>(optional)</span>
        <input type="text" name="alt_text" maxlength="500" placeholder="Useful for a single image; bulk uploads can be edited after upload">
      </label>
      <div class="media-upload-actions">
        <button class="secondary-button" type="button" data-upload-cancel>Cancel</button>
        <button class="primary-button" type="submit">Upload selected</button>
      </div>
    </form>
  </div>

  <div class="panel media-library-panel">
    <div class="media-toolbar">
      <form method="get" action="<?= e(url('admin/media')) ?>" class="media-search" role="search">
        <span aria-hidden="true">⌕</span>
        <input type="search" name="q" value="<?= e($query ?? '') ?>" placeholder="Search filename or alt text…" aria-label="Search media" data-media-search>
        <?php if(!empty($query)):?><a href="<?= e(url('admin/media')) ?>" class="media-search-clear" aria-label="Clear search">×</a><?php endif;?>
      </form>

      <div class="media-filter-group">
        <select aria-label="Filter by usage" data-media-filter="usage">
          <option value="all">All usage</option>
          <option value="used">In use</option>
          <option value="unused">Unused</option>
        </select>
        <select aria-label="Filter by alt text" data-media-filter="alt">
          <option value="all">All alt text</option>
          <option value="missing">Missing alt</option>
          <option value="present">Has alt</option>
        </select>
        <select aria-label="Filter by file type" data-media-filter="type">
          <option value="all">All types</option>
          <option value="jpeg">JPEG</option>
          <option value="png">PNG</option>
          <option value="webp">WebP</option>
          <option value="gif">GIF</option>
        </select>
        <select aria-label="Sort media" data-media-sort>
          <option value="newest">Newest first</option>
          <option value="oldest">Oldest first</option>
          <option value="name">Name A–Z</option>
          <option value="largest">Largest first</option>
        </select>
      </div>

      <div class="media-view-toggle" aria-label="Media view">
        <button type="button" class="is-active" data-media-view="grid" aria-label="Grid view" title="Grid view">▦</button>
        <button type="button" data-media-view="list" aria-label="List view" title="List view">☷</button>
      </div>
    </div>

    <?php if (empty($items)): ?>
      <div class="media-empty">
        <div class="media-empty-icon" aria-hidden="true">▧</div>
        <h3><?= !empty($query)?'No matching media':'Your library is empty' ?></h3>
        <p><?= !empty($query)?'Try a different filename or alt-text search.':'Upload your first images and they will appear here.' ?></p>
        <?php if(!empty($query)):?><a class="secondary-button" href="<?= e(url('admin/media')) ?>">Clear search</a><?php else:?><button type="button" class="primary-button" data-upload-toggle>Upload images</button><?php endif;?>
      </div>
    <?php else: ?>
      <div class="media-selection-row">
        <label><input type="checkbox" data-select-visible> Select visible</label>
        <span><strong data-visible-count><?= $total ?></strong> shown</span>
      </div>

      <div class="media-grid" data-media-grid>
        <?php foreach ($items as $item):
          $displayPath=$item['thumbnail_path'] ?: $item['file_path'];
          $imageUrl=url(ltrim($item['file_path'],'/'));
          $usage=$item['usage']??[];
          $used=!empty($usage);
          $altText=trim((string)($item['alt_text'] ?? ''));
          $mimeSubtype=strtolower((string)str_replace('image/','',$item['mime_type'] ?? ''));
          $createdAt=(string)($item['created_at'] ?? '');
          $timestamp=$createdAt!==''?(int)(strtotime($createdAt)?:0):0;
          $usageText=$used?implode(', ',$usage):'Not currently used by CMS content';
        ?>
          <article class="media-card"
            data-media-card
            data-name="<?= e(strtolower((string)$item['original_name'])) ?>"
            data-search="<?= e(strtolower((string)$item['original_name'].' '.$altText)) ?>"
            data-usage="<?= $used?'used':'unused' ?>"
            data-alt="<?= $altText===''?'missing':'present' ?>"
            data-type="<?= e($mimeSubtype) ?>"
            data-size="<?= (int)$item['file_size'] ?>"
            data-created="<?= $timestamp ?>"
            data-id="<?= (int)$item['id'] ?>"
            data-url="<?= e($imageUrl) ?>"
            data-original-name="<?= e((string)$item['original_name']) ?>"
            data-alt-text="<?= e($altText) ?>"
            data-dimensions="<?= (int)$item['width'] ?>×<?= (int)$item['height'] ?>"
            data-file-size="<?= e($formatBytes((int)$item['file_size'])) ?>"
            data-uploader="<?= e((string)($item['uploader_name'] ?? '')) ?>"
            data-created-label="<?= e($createdAt) ?>"
            data-usage-label="<?= e($usageText) ?>"
            data-optimized="<?= !empty($item['optimized'])?'1':'0' ?>"
            data-can-delete="<?= $canManageProducts && !$used?'1':'0' ?>">
            <div class="media-card-select">
              <input type="checkbox" name="ids[]" value="<?= (int)$item['id'] ?>" form="media-bulk-delete" aria-label="Select <?= e((string)$item['original_name']) ?>" data-media-select>
            </div>
            <button type="button" class="media-thumb" data-media-details aria-label="View details for <?= e((string)$item['original_name']) ?>">
              <img src="<?= e(url(ltrim($displayPath,'/'))) ?>" alt="<?= e($altText ?: $item['original_name']) ?>" loading="lazy">
              <span class="media-thumb-overlay">View details</span>
            </button>
            <div class="media-card-body">
              <div class="media-card-title-row">
                <strong title="<?= e((string)$item['original_name']) ?>"><?= e((string)$item['original_name']) ?></strong>
                <span class="media-type"><?= e(strtoupper($mimeSubtype)) ?></span>
              </div>
              <small><?= (int)$item['width'] ?>×<?= (int)$item['height'] ?> · <?= e($formatBytes((int)$item['file_size'])) ?></small>
              <div class="media-badges">
                <?php if($used):?><span class="media-badge used">● In use</span><?php else:?><span class="media-badge unused">Unused</span><?php endif;?>
                <?php if($altText===''):?><span class="media-badge warning">Alt missing</span><?php endif;?>
                <?php if(!empty($item['optimized'])):?><span class="media-badge optimized">Optimized</span><?php endif;?>
              </div>
              <div class="media-card-actions">
                <button type="button" class="media-icon-button" data-copy-url title="Copy image URL" aria-label="Copy image URL">⧉</button>
                <button type="button" class="media-text-button" data-media-details>Details</button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="media-no-results" data-media-no-results hidden>
        <h3>No assets match these filters</h3>
        <p>Clear a filter or search term to see more images.</p>
        <button type="button" class="secondary-button" data-clear-filters>Clear filters</button>
      </div>
    <?php endif; ?>
  </div>

  <?php if(!empty($items)): ?>
  <form method="post" action="<?= e(url('admin/media/delete-bulk')) ?>" id="media-bulk-delete" class="media-bulk-bar" data-bulk-bar hidden onsubmit="return confirm('Delete all selected unused images? Images that are in use will be kept.')">
    <?= Csrf::field() ?>
    <span><strong data-selected-count>0</strong> selected</span>
    <button type="button" class="secondary-button" data-copy-selected>Copy URLs</button>
    <?php if($canManageProducts):?><button type="submit" class="media-danger-button">Delete unused selected</button><?php endif;?>
    <button type="button" class="media-bulk-close" data-clear-selection aria-label="Clear selection">×</button>
  </form>
  <?php endif; ?>

  <dialog class="media-dialog" data-media-dialog>
    <button type="button" class="media-dialog-close" data-dialog-close aria-label="Close">×</button>
    <div class="media-dialog-layout">
      <div class="media-dialog-preview"><img src="" alt="" data-dialog-image></div>
      <div class="media-dialog-content">
        <div>
          <span class="media-eyebrow">Asset details</span>
          <h3 data-dialog-name></h3>
          <p class="muted" data-dialog-meta></p>
        </div>

        <div class="media-detail-row"><span>Usage</span><strong data-dialog-usage></strong></div>
        <div class="media-detail-row"><span>Uploaded by</span><strong data-dialog-uploader></strong></div>
        <div class="media-detail-row"><span>Uploaded</span><strong data-dialog-created></strong></div>

        <div class="media-url-box">
          <input type="text" readonly value="" data-dialog-url aria-label="Image URL">
          <button type="button" class="secondary-button" data-dialog-copy>Copy URL</button>
        </div>

        <form method="post" action="<?= e(url('admin/media/update-alt')) ?>" class="media-detail-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="" data-dialog-id>
          <label>Alt text
            <textarea name="alt_text" maxlength="500" rows="3" placeholder="Describe what the image shows and why it matters" data-dialog-alt></textarea>
          </label>
          <div class="media-form-hint">Keep it concise and useful for someone who cannot see the image.</div>
          <button class="secondary-button">Save alt text</button>
        </form>

        <?php if($canManageProducts && !empty($categories)): ?>
        <form method="post" action="<?= e(url('admin/media/assign-category')) ?>" class="media-detail-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="image_url" value="" data-dialog-category-url>
          <label>Use as category image
            <select name="category_id" required>
              <option value="">Choose category</option>
              <?php foreach($categories as $category):?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach;?>
            </select>
          </label>
          <button class="secondary-button">Set category image</button>
        </form>
        <?php endif; ?>

        <?php if($canManageProducts): ?>
        <form method="post" action="<?= e(url('admin/media/delete')) ?>" class="media-delete-form" data-dialog-delete onsubmit="return confirm('Delete this image permanently? This cannot be undone.')">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="" data-dialog-delete-id>
          <button class="media-danger-button">Delete image</button>
          <small data-delete-note>Only unused images can be deleted.</small>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </dialog>

  <div class="media-toast" role="status" aria-live="polite" data-media-toast hidden></div>
</section>
