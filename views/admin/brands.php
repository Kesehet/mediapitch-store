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
      <label>Slug<input required name="slug" maxlength="180" placeholder="lg" value="<?= e($brand['slug'] ?? '') ?>"></label>
      <label>Website URL<input type="url" name="website_url" maxlength="1000" placeholder="https://example.com" value="<?= e($brand['website_url'] ?? '') ?>"></label>
      <label>Logo URL<input type="url" name="logo_url" maxlength="1000" placeholder="https://..." value="<?= e($brand['logo_url'] ?? '') ?>"></label>
      <div class="form-actions">
        <button class="button primary" type="submit">Save brand</button>
        <?php if ($editing): ?><a class="button" href="<?= e(url('admin/brands')) ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Brands</h2><span><?= count($brands) ?> total</span></div>
    <?php if (!$brands): ?>
      <p class="empty">No brands yet.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Name</th><th>Slug</th><th>Website</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($brands as $item): ?>
          <tr>
            <td><strong><?= e($item['name']) ?></strong></td>
            <td><code><?= e($item['slug']) ?></code></td>
            <td><?php if (!empty($item['website_url'])): ?><a href="<?= e($item['website_url']) ?>" target="_blank" rel="noopener noreferrer">Visit ↗</a><?php else: ?>—<?php endif; ?></td>
            <td><a href="<?= e(url('admin/brands?edit=' . (int)$item['id'])) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
