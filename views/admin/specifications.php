<?php
use MediaPitch\Core\Csrf;
$d = $definition ?? [];
$options = [];
if (!empty($d['options_json'])) {
    $decoded = json_decode((string)$d['options_json'], true);
    if (is_array($decoded)) $options = $decoded;
}
?>
<div class="admin-split">
  <section class="panel">
    <div class="panel-head"><div><h2>Specification definitions</h2><p class="muted">Define the fields that belong to each product category.</p></div></div>
    <?php if (!$definitions): ?>
      <p class="muted">No specifications yet. Add the first one using the form.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="admin-table"><thead><tr><th>Category</th><th>Name</th><th>Type</th><th>Unit</th><th>Flags</th><th></th></tr></thead><tbody>
      <?php foreach ($definitions as $row): ?>
        <tr>
          <td><?= e($row['category_name']) ?></td>
          <td><strong><?= e($row['name']) ?></strong><small><?= e($row['slug']) ?></small></td>
          <td><?= e($row['data_type']) ?></td>
          <td><?= e($row['unit'] ?? '—') ?></td>
          <td><?= !empty($row['filterable']) ? 'Filter ' : '' ?><?= !empty($row['comparable']) ? 'Compare' : '' ?></td>
          <td><a href="<?= e(url('admin/specifications?edit=' . (int)$row['id'])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="panel form-panel compact-form">
    <h2><?= !empty($d['id']) ? 'Edit specification' : 'Add specification' ?></h2>
    <form method="post" action="<?= e(url('admin/specifications/save')) ?>">
      <?= Csrf::field() ?>
      <?php if (!empty($d['id'])): ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><?php endif; ?>
      <label>Category<select name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($d['category_id'] ?? 0)===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
      <label>Name<input name="name" required value="<?= e($d['name'] ?? '') ?>" placeholder="Energy rating"></label>
      <label>Slug<input name="slug" required value="<?= e($d['slug'] ?? '') ?>" placeholder="energy-rating"></label>
      <div class="form-grid">
        <label>Type<select name="data_type" id="spec-data-type"><option value="text" <?= ($d['data_type'] ?? 'text')==='text'?'selected':'' ?>>Text</option><option value="number" <?= ($d['data_type'] ?? '')==='number'?'selected':'' ?>>Number</option><option value="boolean" <?= ($d['data_type'] ?? '')==='boolean'?'selected':'' ?>>Yes / No</option><option value="select" <?= ($d['data_type'] ?? '')==='select'?'selected':'' ?>>Select list</option></select></label>
        <label>Unit<input name="unit" value="<?= e($d['unit'] ?? '') ?>" placeholder="Ton, W, inch..."></label>
        <label>Sort order<input type="number" name="sort_order" value="<?= (int)($d['sort_order'] ?? 0) ?>"></label>
      </div>
      <label id="spec-options-wrap">Select options <small>one per line; required for select lists</small><textarea name="options" rows="6"><?= e(implode("\n", $options)) ?></textarea></label>
      <label class="check"><input type="checkbox" name="filterable" value="1" <?= !empty($d['filterable'])?'checked':'' ?>> Use as a public filter</label>
      <label class="check"><input type="checkbox" name="comparable" value="1" <?= !isset($d['comparable'])||!empty($d['comparable'])?'checked':'' ?>> Show in product comparisons</label>
      <div class="form-actions"><?php if (!empty($d['id'])): ?><a class="secondary-button" href="<?= e(url('admin/specifications')) ?>">Cancel</a><?php endif; ?><button class="primary-button">Save specification</button></div>
    </form>
  </section>
</div>
<script>
(function(){
  const type=document.getElementById('spec-data-type');
  const wrap=document.getElementById('spec-options-wrap');
  function toggle(){wrap.style.display=type.value==='select'?'block':'none';}
  type.addEventListener('change',toggle); toggle();
})();
</script>
