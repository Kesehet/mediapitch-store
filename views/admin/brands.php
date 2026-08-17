<?php
use MediaPitch\Core\Csrf;
$editing = !empty($brand);
?>
<div class="admin-grid two">
  <section class="panel">
    <div class="panel-head"><h2><?= $editing ? 'Edit brand' : 'Add brand' ?></h2></div>
    <form method="post" action="<?= e(url('admin/brands/save')) ?>" class="admin-form">
      <?= Csrf::field() ?>
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$brand['id'] ?>"><?php endif; ?>
      <label>Name<input required name="name" maxlength="150" value="<?= e($brand['name'] ?? '') ?>"></label>
      <label>Slug<input required name="slug" maxlength="180" value="<?= e($brand['slug'] ?? '') ?>"></label>
      <label>Website URL<input type="url" name="website_url" maxlength="1000" placeholder="https://example.com" value="<?= e($brand['website_url'] ?? '') ?>"></label>
      <?php if(!empty($mediaItems)): ?>
        <label>Choose logo from media library
          <select id="brand-logo-picker"><option value="">— Select uploaded image —</option><?php foreach($mediaItems as $media): $mediaUrl=url(ltrim((string)$media['file_path'],'/')); ?><option value="<?= e($mediaUrl) ?>" <?= ($brand['logo_url']??'')===$mediaUrl?'selected':'' ?>><?= e($media['original_name']) ?><?= !empty($media['alt_text'])?' — '.e($media['alt_text']):'' ?></option><?php endforeach; ?></select>
          <small><a href="<?= e(url('admin/media')) ?>" target="_blank" rel="noopener">Open media library ↗</a></small>
        </label>
      <?php endif; ?>
      <label>Logo URL<input id="brand-logo-url" type="url" name="logo_url" maxlength="1000" placeholder="https://..." value="<?= e($brand['logo_url'] ?? '') ?>"></label>
      <?php if(!empty($brand['logo_url'])): ?><div><img src="<?= e($brand['logo_url']) ?>" alt="<?= e($brand['name']??'Brand') ?> logo" style="max-width:180px;max-height:80px;object-fit:contain"></div><?php endif; ?>
      <div class="form-actions"><button class="primary-button" type="submit">Save brand</button><?php if ($editing): ?><a class="secondary-button" href="<?= e(url('admin/brands')) ?>">Cancel</a><?php endif; ?></div>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Brands</h2><span><?= count($brands) ?> total</span></div>
    <?php if (!$brands): ?><p class="empty">No brands yet.</p><?php else: ?>
      <div class="table-wrap"><table class="data-table"><thead><tr><th>Brand</th><th>Status</th><th>Slug</th><th>Website</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($brands as $item): ?>
          <tr>
            <td><?php if(!empty($item['logo_url'])):?><img src="<?= e($item['logo_url']) ?>" alt="" style="width:44px;height:32px;object-fit:contain;vertical-align:middle;margin-right:8px"><?php endif; ?><strong><?= e($item['name']) ?></strong></td>
            <td><?= !empty($item['active'])?'Active':'Archived' ?></td>
            <td><code><?= e($item['slug']) ?></code></td>
            <td><?php if (!empty($item['website_url'])): ?><a href="<?= e($item['website_url']) ?>" target="_blank" rel="noopener noreferrer">Visit ↗</a><?php else: ?>—<?php endif; ?></td>
            <td><div class="row-actions"><a href="<?= e(url('admin/brands?edit=' . (int)$item['id'])) ?>">Edit</a><form method="post" action="<?= e(url('admin/brands/'.(int)$item['id'].'/'.(!empty($item['active'])?'archive':'restore'))) ?>"><?= Csrf::field() ?><button class="link-button" type="submit" <?= !empty($item['active'])?'onclick="return confirm(\'Archive this brand? Existing product links will be preserved.\')"':'' ?>><?= !empty($item['active'])?'Archive':'Restore' ?></button></form></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<script>(function(){const picker=document.getElementById('brand-logo-picker'),input=document.getElementById('brand-logo-url');if(picker&&input)picker.addEventListener('change',()=>{if(picker.value)input.value=picker.value;});})();</script>
