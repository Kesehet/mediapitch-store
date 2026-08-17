<?php use MediaPitch\Core\Csrf; ?>
<section class="panel">
  <div class="panel-head"><div><h2>Products</h2><p>Manual, Amazon API and hybrid product records.</p></div><a class="primary-button" href="<?= e(url('admin/products/new')) ?>">+ Add product</a></div>
  <table class="data-table"><thead><tr><th>Product</th><th>Category</th><th>Source</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($products as $p): ?>
    <tr>
      <td><strong><?= e($p['display_title'] ?: $p['title']) ?></strong><small><?= e($p['brand_name'] ?? '') ?></small></td>
      <td><?= e($p['category_name'] ?? '—') ?></td>
      <td><span class="badge"><?= e($p['source']) ?></span></td>
      <td><?= $p['price']!==null ? e(($p['currency'] ?? 'INR').' '.number_format((float)$p['price'],2)) : '—' ?></td>
      <td><?= !empty($p['active'])?'Active':'Archived' ?></td>
      <td>
        <div class="table-actions">
          <a href="<?= e(url('admin/products/'.(int)$p['id'].'/edit')) ?>">Edit</a>
          <form method="post" action="<?= e(url('admin/products/'.(int)$p['id'].'/duplicate')) ?>" class="inline-form"><?= Csrf::field() ?><button class="link-button">Duplicate</button></form>
          <?php if(!empty($p['active'])): ?>
            <form method="post" action="<?= e(url('admin/products/'.(int)$p['id'].'/archive')) ?>" class="inline-form" onsubmit="return confirm('Archive this product? It will disappear from public pages but remain editable.')"><?= Csrf::field() ?><button class="link-button danger-link">Archive</button></form>
          <?php else: ?>
            <form method="post" action="<?= e(url('admin/products/'.(int)$p['id'].'/restore')) ?>" class="inline-form"><?= Csrf::field() ?><button class="link-button">Restore</button></form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if(!$products): ?><tr><td colspan="6" class="empty">No products yet.</td></tr><?php endif; ?>
  </tbody></table>
</section>
