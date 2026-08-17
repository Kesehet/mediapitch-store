<?php use MediaPitch\Core\Csrf; $s=$settings??[]; $featured=array_flip($s['featured_ids']??[]); $deals=array_flip($s['deal_ids']??[]); ?>
<form method="post" action="<?= e(url('admin/merchandising/save')) ?>" class="panel form-panel">
  <?= Csrf::field() ?>
  <div class="panel-head"><div><h2>Curate the homepage</h2><p class="muted">Choose which active products should be highlighted. Order follows the product list below for now.</p></div><button class="primary-button">Save merchandising</button></div>
  <label>Deals section title<input name="deals_title" maxlength="150" value="<?= e($s['deals_title']??'Deals worth a look') ?>"></label>
  <div class="table-wrap" style="margin-top:18px"><table class="admin-table"><thead><tr><th>Product</th><th>Category</th><th>Source</th><th>Featured</th><th>Deal</th></tr></thead><tbody>
  <?php foreach($products as $product): $id=(int)$product['id']; ?>
    <tr>
      <td><strong><?= e($product['title']) ?></strong><?php if(empty($product['active'])):?><small>Inactive</small><?php endif;?></td>
      <td><?= e($product['category_name']??'—') ?></td>
      <td><?= e($product['source']) ?></td>
      <td><input type="checkbox" name="featured_ids[]" value="<?= $id ?>" <?= isset($featured[$id])?'checked':'' ?> <?= empty($product['active'])?'disabled':'' ?>></td>
      <td><input type="checkbox" name="deal_ids[]" value="<?= $id ?>" <?= isset($deals[$id])?'checked':'' ?> <?= empty($product['active'])?'disabled':'' ?>></td>
    </tr>
  <?php endforeach;?>
  </tbody></table></div>
  <div class="form-actions"><button class="primary-button">Save merchandising</button></div>
</form>
