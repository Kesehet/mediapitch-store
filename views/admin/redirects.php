<?php
use MediaPitch\Core\Csrf;
$editing=!empty($editRedirect);
$healthResults=is_array($healthResults??null)?$healthResults:[];
?>
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
  <div class="panel-head">
    <div><h2>Redirects</h2><p><?= count($redirects) ?> configured. Health checks follow each destination with server-side cURL; only HTTP 404/410 is auto-disabled.</p></div>
    <form method="post" action="<?= e(url('admin/redirects/check')) ?>" onsubmit="return confirm('Check all active redirect targets now? Broken 404/410 redirects will be disabled automatically.')">
      <?= Csrf::field() ?>
      <button class="secondary-button" type="submit">Check active redirects</button>
    </form>
  </div>
  <div class="table-wrap">
  <table class="data-table"><thead><tr><th>From</th><th>To</th><th>Status</th><th>Health</th><th></th></tr></thead><tbody>
  <?php foreach($redirects as $row):
      $id=(int)$row['id'];
      $health=$healthResults[$id]??null;
  ?><tr>
      <td><code><?= e($row['from_path']) ?></code></td>
      <td><small><?= e($row['to_url']) ?></small></td>
      <td><?= !empty($row['active'])?e((string)$row['status_code']):'Inactive' ?></td>
      <td>
        <?php if($health):
            $http=$health['http_status']??null;
            $state=(string)($health['state']??'warning');
        ?>
          <span class="badge"><?= e($state==='healthy'?'Healthy':($state==='broken'?'Broken':'Warning')) ?></span>
          <small><?= $http!==null?'HTTP '.e((string)$http):e((string)($health['error']??'No HTTP response')) ?><?= !empty($health['disabled'])?' · disabled':'' ?></small>
        <?php else: ?><span class="muted">Not checked this session</span><?php endif;?>
      </td>
      <td>
        <a href="<?= e(url('admin/redirects?edit='.$id)) ?>">Edit</a> ·
        <form method="post" action="<?= e(url('admin/redirects/'.$id.'/check')) ?>" style="display:inline">
          <?= Csrf::field() ?><button type="submit" class="text-link" style="border:0;background:none;padding:0;cursor:pointer">Check</button>
        </form> ·
        <form method="post" action="<?= e(url('admin/redirects/'.$id.'/delete')) ?>" style="display:inline" onsubmit="return confirm('Permanently delete this redirect?')">
          <?= Csrf::field() ?><button type="submit" class="text-link" style="border:0;background:none;padding:0;cursor:pointer">Delete</button>
        </form>
      </td>
    </tr><?php endforeach;?>
  <?php if(!$redirects):?><tr><td colspan="5" class="empty">No redirects yet.</td></tr><?php endif;?>
  </tbody></table>
  </div>
</section>
</div>
