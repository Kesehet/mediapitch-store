<?php
use MediaPitch\Core\Csrf;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($pageTitle ?? 'Admin') ?> — MediaPitch</title><link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>"></head>
<body class="admin-body">
<aside class="admin-sidebar">
  <a class="admin-brand" href="<?= e(url('admin')) ?>">MediaPitch <span>CMS</span></a>
  <nav>
    <a class="<?= $currentPath==='/admin'?'active':'' ?>" href="<?= e(url('admin')) ?>">Dashboard</a>
    <a class="<?= str_starts_with($currentPath,'/admin/products')?'active':'' ?>" href="<?= e(url('admin/products')) ?>">Products</a>
    <a class="<?= str_starts_with($currentPath,'/admin/categories')?'active':'' ?>" href="<?= e(url('admin/categories')) ?>">Categories</a>
    <a class="<?= str_starts_with($currentPath,'/admin/brands')?'active':'' ?>" href="<?= e(url('admin/brands')) ?>">Brands</a>
    <a class="<?= str_starts_with($currentPath,'/admin/specifications')?'active':'' ?>" href="<?= e(url('admin/specifications')) ?>">Specifications</a>
    <a class="<?= str_starts_with($currentPath,'/admin/guides')?'active':'' ?>" href="<?= e(url('admin/guides')) ?>">Buying Guides</a>
    <a class="<?= str_starts_with($currentPath,'/admin/comparisons')?'active':'' ?>" href="<?= e(url('admin/comparisons')) ?>">Comparisons</a>
    <a class="<?= str_starts_with($currentPath,'/admin/reviews')?'active':'' ?>" href="<?= e(url('admin/reviews')) ?>">Reviews</a>
    <a class="<?= str_starts_with($currentPath,'/admin/blog')?'active':'' ?>" href="<?= e(url('admin/blog')) ?>">Blog</a>
    <a class="<?= str_starts_with($currentPath,'/admin/media')?'active':'' ?>" href="<?= e(url('admin/media')) ?>">Media</a>
    <?php if(($adminUser['role']??'')==='administrator'):?><a class="<?= str_starts_with($currentPath,'/admin/settings')?'active':'' ?>" href="<?= e(url('admin/settings/amazon')) ?>">Amazon Settings</a><?php endif;?>
  </nav>
  <div class="admin-user"><strong><?= e($adminUser['name'] ?? '') ?></strong><small><?= e($adminUser['role'] ?? '') ?></small>
    <form method="post" action="<?= e(url('admin/logout')) ?>"><?= Csrf::field() ?><button class="link-button">Sign out</button></form>
  </div>
</aside>
<main class="admin-main"><header class="admin-top"><h1><?= e($pageTitle ?? 'Admin') ?></h1><a href="<?= e(url()) ?>" target="_blank" rel="noopener">View site ↗</a></header>
<?php if (!empty($success)): ?><div class="flash success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
<?= $content ?>
</main></body></html>
