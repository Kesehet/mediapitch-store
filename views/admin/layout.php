<?php
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\Database;
use MediaPitch\Services\ProductOverrides;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$helpMode = $currentPath==='/admin' && !empty($_GET['help']);
$adminCssVersion = (string) @filemtime(dirname(__DIR__, 2) . '/public/assets/admin.css');
$browserTitle = str_replace(' — ', ' | ', ($helpMode ? 'Documentation' : ($pageTitle ?? 'Admin')) . ' | MediaPitch');
$canCatalog=Auth::canManageProducts();
$canContent=Auth::canEditContent();
$canMedia=Auth::canUploadMedia();
$isAdmin=Auth::isAdministrator();
$productSyncState=null;
if($canCatalog && preg_match('#^/admin/products/(\d+)/edit$#',$currentPath,$syncMatch)){
  try{
    $stmt=Database::connection()->prepare('SELECT id,source,asin,api_marketplace,last_synced_at,manual_override_json FROM products WHERE id=:id LIMIT 1');
    $stmt->execute(['id'=>(int)$syncMatch[1]]);
    $syncProduct=$stmt->fetch(PDO::FETCH_ASSOC);
    if($syncProduct){
      $productSyncState=[
        'id'=>(int)$syncProduct['id'],
        'source'=>(string)$syncProduct['source'],
        'asin'=>(string)($syncProduct['asin']??''),
        'marketplace'=>(string)($syncProduct['api_marketplace']??''),
        'last_synced_at'=>$syncProduct['last_synced_at']?:null,
        'overrides'=>(new ProductOverrides())->forProduct($syncProduct),
      ];
    }
  }catch(Throwable $syncError){
    if((bool)env('APP_DEBUG',false))error_log('Product sync UI state failed: '.$syncError->getMessage());
  }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($browserTitle) ?></title><link rel="stylesheet" href="/assets/admin.css?v=<?= e($adminCssVersion) ?>"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jodit@4.13.23/es2021/jodit.min.css"><link rel="stylesheet" href="/assets/admin-editor.css"><script src="https://cdn.jsdelivr.net/npm/jodit@4.13.23/es2021/jodit.min.js" defer></script><script src="/assets/admin-editor.js" defer></script><script src="/assets/product-sync.js" defer></script><?php if($productSyncState): ?><script>window.MediaPitchProductSync=<?= json_encode($productSyncState,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?></head>
<body class="admin-body">
<aside class="admin-sidebar">
  <a class="admin-brand" href="<?= e(url('admin')) ?>">MediaPitch <span>CMS</span></a>
  <nav>
    <a class="<?= $currentPath==='/admin'&&!$helpMode?'active':'' ?>" href="<?= e(url('admin')) ?>">Dashboard</a>
    <a class="<?= $helpMode?'active':'' ?>" href="<?= e(url('admin').'?help=1') ?>">Documentation</a>
    <?php if($canCatalog):?>
      <a class="<?= str_starts_with($currentPath,'/admin/products')?'active':'' ?>" href="<?= e(url('admin/products')) ?>">Products</a>
      <a class="<?= str_starts_with($currentPath,'/admin/merchandising')?'active':'' ?>" href="<?= e(url('admin/merchandising')) ?>">Homepage Picks</a>
      <a class="<?= str_starts_with($currentPath,'/admin/categories')?'active':'' ?>" href="<?= e(url('admin/categories')) ?>">Categories</a>
      <a class="<?= str_starts_with($currentPath,'/admin/brands')?'active':'' ?>" href="<?= e(url('admin/brands')) ?>">Brands</a>
      <a class="<?= str_starts_with($currentPath,'/admin/specifications')?'active':'' ?>" href="<?= e(url('admin/specifications')) ?>">Specifications</a>
    <?php endif;?>
    <?php if($canContent):?>
      <a class="<?= str_starts_with($currentPath,'/admin/guides')?'active':'' ?>" href="<?= e(url('admin/guides')) ?>">Buying Guides</a>
      <a class="<?= str_starts_with($currentPath,'/admin/comparisons')?'active':'' ?>" href="<?= e(url('admin/comparisons')) ?>">Comparisons</a>
      <a class="<?= str_starts_with($currentPath,'/admin/reviews')?'active':'' ?>" href="<?= e(url('admin/reviews')) ?>">Reviews</a>
      <a class="<?= str_starts_with($currentPath,'/admin/blog')?'active':'' ?>" href="<?= e(url('admin/blog')) ?>">Blog</a>
    <?php endif;?>
    <?php if($canMedia):?><a class="<?= str_starts_with($currentPath,'/admin/media')?'active':'' ?>" href="<?= e(url('admin/media')) ?>">Media</a><?php endif;?>
    <?php if($isAdmin):?><a class="<?= str_starts_with($currentPath,'/admin/analytics')?'active':'' ?>" href="<?= e(url('admin/analytics')) ?>">Analytics</a><?php endif;?>
    <?php if($isAdmin):?><a class="<?= str_starts_with($currentPath,'/admin/audit')?'active':'' ?>" href="<?= e(url('admin/audit')) ?>">Audit Log</a><?php endif;?>
    <?php if($isAdmin):?><a class="<?= str_starts_with($currentPath,'/admin/redirects')?'active':'' ?>" href="<?= e(url('admin/redirects')) ?>">Redirects</a><?php endif;?>
    <?php if($isAdmin):?><a class="<?= str_starts_with($currentPath,'/admin/users')?'active':'' ?>" href="<?= e(url('admin/users')) ?>">Users</a><?php endif;?>
    <?php if($isAdmin):?><a class="<?= $currentPath==='/admin/settings/site'?'active':'' ?>" href="<?= e(url('admin/settings/site')) ?>">Website Settings</a><?php endif;?>
    <?php if($isAdmin):?><a class="<?= str_starts_with($currentPath,'/admin/settings/amazon')?'active':'' ?>" href="<?= e(url('admin/settings/amazon')) ?>">Amazon Settings</a><?php endif;?>
  </nav>
  <div class="admin-user"><strong><?= e($adminUser['name'] ?? '') ?></strong><small><?= e($adminUser['role'] ?? '') ?></small>
    <a class="link-button" href="<?= e(url('admin/account')) ?>">My account</a>
    <form method="post" action="<?= e(url('admin/logout')) ?>"><?= Csrf::field() ?><button class="link-button">Sign out</button></form>
  </div>
</aside>
<main class="admin-main"><header class="admin-top"><h1><?= e($helpMode ? 'Documentation' : ($pageTitle ?? 'Admin')) ?></h1><a href="<?= e(url()) ?>" target="_blank" rel="noopener">View site ↗</a></header>
<?php if (!empty($success)): ?><div class="flash success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
<?= $content ?>
</main></body></html>
