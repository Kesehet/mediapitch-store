<?php use MediaPitch\Core\Csrf; ?>
<div class="panel form-panel" style="max-width:720px">
  <div class="panel-head"><div><h2>Change password</h2><p>Update the password for your currently signed-in account.</p></div></div>
  <form method="post" action="<?= e(url('admin/account/password')) ?>" class="stack-form">
    <?= Csrf::field() ?>
    <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label>New password<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
    <label>Confirm new password<input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password"></label>
    <div class="form-actions"><button class="primary-button" type="submit">Change password</button></div>
  </form>
</div>
