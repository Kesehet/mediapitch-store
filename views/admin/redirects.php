<?php use MediaPitch\Core\Csrf; $editing=!empty($editRedirect); ?>
<div class="two-col">
<section class="panel form-panel">
  <div class="panel-head"><div><h2><?= $editing?'Edit redirect':'Add redirect' ?></h2><p>Keep old URLs working when content moves or slugs change.</p></div></div>
  <form method="post" action="<?= e(url('admin/redirects/save')) ?>" class="stack-form">
    <?= Csrf::field() ?><?php if($editing):?><input type="hidden" name="id" value="<?= (int)$editRedirect['id'] ?>"><?php endif;?>
    <label>From path<input name="from_path" required placeholder="/old-product-url" value="<?= e($editRedirect['from_path']??'') ?>"></label>
    <label>Destination<input name="to_url" required placeholder="/product/new-url or https://..." value="<?= e($editRedirect['to_url']??'') ?>"></label>
    <label>Status<select name="status_code"><option value="301" <?= (int)($editRedirect['status_code']??301)===301?'selected':'' ?>>301 Permanent</option><option value="302" <?= (int)($editRedirect['status_code']??0)===302?'selected':'' ?>>302 Temporary</option><option value="307" <?= (int)($editRedirect['status_code']??0)===307?'selected':'' ?>>307 Temporary</option><option value="308" <?= (int)($editRedirect['status_code']??0)===308?'selected':'' ?>>308 Permanent</option></select></label>
    <label class="check"><input type="checkbox" name="active" value="1" <?= !isset($editRedirect['active'])||!empty($editRedirect['active'])?'checked':'' ?>> Active</label>
    <div class="form-actions"><?php if($editing):?><a class="secondary-button" href="<?= e(url('admin/redirects')) ?>">Cancel</a><?php endif;?><button class="primary-button">Save redirect</button></div>
  </form>
</section>
<section class="panel">
  <div class="panel-head"><div><h2>Redirects</h2><p><?= count($redirects) ?> configured</p></div></div>
  <table class="data-table"><thead><tr><th>From</th><th>To</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($redirects as $row):?><tr><td><code><?= e($row['from_path']) ?></code></td><td><small><?= e($row['to_url']) ?></small></td><td><?= !empty($row['active'])?e((string)$row['status_code']):'Inactive' ?></td><td><a href="<?= e(url('admin/redirects?edit='.(int)$row['id'])) ?>">Edit</a></td></tr><?php endforeach;?>
  <?php if(!$redirects):?><tr><td colspan="4" class="empty">No redirects yet.</td></tr><?php endif;?>
  </tbody></table>
</section>
</div>
