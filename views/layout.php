<?php
$pageTitle = str_replace(' — ', ' | ', $pageTitle ?? 'MediaPitch Store');
$metaDescription = $metaDescription ?? 'Independent product recommendations and buying guides.';
$robotsIndex = $robotsIndex ?? true;
$canonicalUrl = $canonicalUrl ?? null;
$siteSettings=$siteSettings??[];
$siteName=(string)($siteSettings['name']??'MediaPitch Store');
$siteTagline=(string)($siteSettings['tagline']??'Independent buying guides, comparisons and product discovery.');
$affiliateDisclosure=(string)($siteSettings['affiliate_disclosure']??'As an Amazon Associate, MediaPitch may earn from qualifying purchases. Product availability and prices can change on Amazon.');
$ogImage = $ogImage
    ?? ($product['main_image_url'] ?? null)
    ?? ($post['featured_image_url'] ?? null)
    ?? ($guide['featured_image_url'] ?? null)
    ?? ($review['featured_image_url'] ?? null)
    ?? ($review['main_image_url'] ?? null)
    ?? ($comparison['featured_image_url'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="robots" content="<?= $robotsIndex ? 'index,follow' : 'noindex,follow' ?>">
    <?php if ($canonicalUrl): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <?php if ($canonicalUrl): ?><meta property="og:url" content="<?= e($canonicalUrl) ?>"><?php endif; ?>
    <?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <?php if ($ogImage): ?><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="stylesheet" href="/assets/catalog.css">
    <link rel="stylesheet" href="/assets/accessibility.css">
    <script src="/assets/search.js" defer></script>
    <script src="/assets/navigation.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="/" aria-label="<?= e($siteName) ?> home">
            <img src="https://raw.githubusercontent.com/Kesehet/mediapitch/main/images/media_pitchlogo.jpg" alt="MediaPitch" class="brand-logo">
            <span class="brand-store">STORE</span>
        </a>
        <button class="mobile-nav-toggle" type="button" data-mobile-nav-toggle aria-expanded="false" aria-controls="main-nav">Menu</button>
        <nav class="main-nav" id="main-nav" aria-label="Main navigation">
            <a href="/#categories">Categories</a>
            <a href="/#guides">Buying Guides</a>
            <a href="/comparisons">Comparisons</a>
            <a href="/blog">Blog</a>
        </nav>
        <form class="header-search" action="/search" method="get" role="search">
            <input type="search" name="q" placeholder="Search products…" aria-label="Search products" autocomplete="off">
        </form>
    </div>
</header>

<main id="main-content" tabindex="-1">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <strong><?= e($siteName) ?></strong>
            <p><?= e($siteTagline) ?></p>
        </div>
        <div>
            <strong>Affiliate disclosure</strong>
            <p><?= e($affiliateDisclosure) ?></p>
        </div>
    </div>
</footer>
</body>
</html>
