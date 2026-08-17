<?php use MediaPitch\Core\Csrf; ?>
<section class="panel">
  <div class="panel-head"><div><h2>Products</h2><p>Manual, Amazon API and hybrid product records.</p></div><div class="form-actions"><a class="secondary-button" href="<?= e(url('admin/product-tools')) ?>">CSV tools</a><a class="primary-button" href="<?= e(url('admin/products/new')) ?>">+ Add product</a></div></div>
  <form method="post" action="<?= e(url('admin/product-tools/bulk')) ?>"><?= Csrf::field() ?>
    <div class="form-actions" style="justify-content:flex-start;margin-bottom:12px"><select name="bulk_action" required><option value="">Bulk action…</option><option value="archive">Archive selected</option><option value="restore">Restore selected</option></select><button class="secondary-button" type="submit">Apply</button><button class="link-button" type="button" id="select-all-products">Select all</button></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th><span class="sr-only">Select</span></th><th>Product</th><th>Category</th><th>Source</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach($products as $p): ?>
      <tr>
        <td><input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" aria-label="Select <?= e($p['display_title'] ?: $p['title']) ?>"></td>
        <td><strong><?= e($p['display_title'] ?: $p['title']) ?></strong><small><?= e($p['brand_name'] ?? '') ?></small></td>
        <td><?= e($p['category_name'] ?? '—') ?></td>
        <td><span class="badge"><?= e($p['source']) ?></span></td>
        <td><?= $p['price']!==null ? e(($p['currency'] ?? 'INR').' '.number_format((float)$p['price'],2)) : '—' ?></td>
        <td><?= !empty($p['active'])?'Active':'Archived' ?></td>
        <td><div class="table-actions"><a href="<?= e(url('admin/products/'.(int)$p['id'].'/edit')) ?>">Edit</a><button type="submit" class="link-button" form="duplicate-<?= (int)$p['id'] ?>">Duplicate</button><?php if(!empty($p['active'])):?><button type="submit" class="link-button danger-link" form="archive-<?= (int)$p['id'] ?>" onclick="return confirm('Archive this product? It will disappear from public pages but remain editable.')">Archive</button><?php else:?><button type="submit" class="link-button" form="restore-<?= (int)$p['id'] ?>">Restore</button><?php endif;?></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$products): ?><tr><td colspan="7" class="empty">No products yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </form>
  <?php foreach($products as $p): ?>
    <form id="duplicate-<?= (int)$p['id'] ?>" method="post" action="<?= e(url('admin/products/'.(int)$p['id'].'/duplicate')) ?>" hidden><?= Csrf::field() ?></form>
    <?php if(!empty($p['active'])):?><form id="archive-<?= (int)$p['id'] ?>" method="post" action="<?= e(url('admin/products/'.(int)$p['id'].'/archive')) ?>" hidden><?= Csrf::field() ?></form><?php else:?><form id="restore-<?= (int)$p['id'] ?>" method="post" action="<?= e(url('admin/products/'.(int)$p['id'].'/restore')) ?>" hidden><?= Csrf::field() ?></form><?php endif;?>
  <?php endforeach; ?>
</section>
<script>(function(){const button=document.getElementById('select-all-products');if(!button)return;button.addEventListener('click',()=>{const boxes=[...document.querySelectorAll('input[name="product_ids[]"]')];const shouldSelect=boxes.some(box=>!box.checked);boxes.forEach(box=>box.checked=shouldSelect);button.textContent=shouldSelect?'Clear selection':'Select all';});})();</script>
