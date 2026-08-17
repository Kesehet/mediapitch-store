<?php
use MediaPitch\Core\Csrf;
if(empty($success) && isset($_SESSION['_flash']['success']) && is_string($_SESSION['_flash']['success'])){
    $success=$_SESSION['_flash']['success'];
    unset($_SESSION['_flash']['success']);
}
?>
<div class="auth-card">
  <div class="auth-logo">MediaPitch <span>CMS</span></div>
  <h1>Sign in</h1>
  <p>Manage products, buying guides and affiliate content.</p>
  <?php if (!empty($success)): ?><div class="flash success"><?= e($success) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('admin/login')) ?>" class="stack-form">
    <?= Csrf::field() ?>
    <label>Email<input type="email" name="email" required autocomplete="email"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="primary-button" type="submit">Sign in</button>
  </form>
  <p><a href="<?= e(url('admin/forgot-password')) ?>">Forgot your password?</a></p>
</div>
