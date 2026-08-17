<?php use MediaPitch\Core\Csrf; ?>
<div class="auth-card">
  <div class="auth-logo">MediaPitch <span>CMS</span></div>
  <h1>Reset password</h1>
  <p>Enter your account email. If it matches an active user, we’ll send a one-time reset link.</p>
  <?php if(!empty($success)):?><div class="flash success"><?= e($success) ?></div><?php endif;?>
  <?php if(!empty($error)):?><div class="flash error"><?= e($error) ?></div><?php endif;?>
  <form method="post" action="<?= e(url('admin/forgot-password')) ?>" class="stack-form">
    <?= Csrf::field() ?>
    <label>Email<input type="email" name="email" required autocomplete="email"></label>
    <button class="primary-button" type="submit">Send reset link</button>
  </form>
  <p><a href="<?= e(url('admin/login')) ?>">← Back to sign in</a></p>
</div>
