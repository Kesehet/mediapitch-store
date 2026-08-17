<?php use MediaPitch\Core\Csrf; ?>
<div class="auth-card">
  <div class="auth-logo">MediaPitch <span>CMS</span></div>
  <h1>Choose a new password</h1>
  <p>Use at least 8 characters. Reset links are one-time use.</p>
  <?php if(!empty($error)):?><div class="flash error"><?= e($error) ?></div><?php endif;?>
  <form method="post" action="<?= e(url('admin/reset-password')) ?>" class="stack-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= e($token??'') ?>">
    <label>New password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <label>Confirm password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
    <button class="primary-button" type="submit">Reset password</button>
  </form>
  <p><a href="<?= e(url('admin/login')) ?>">← Back to sign in</a></p>
</div>
