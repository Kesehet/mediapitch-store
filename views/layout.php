<?php
$pageTitle = $pageTitle ?? 'MediaPitch Store';
$metaDescription = $metaDescription ?? 'Independent product recommendations and buying guides.';
$robotsIndex = $robotsIndex ?? true;
$canonicalUrl = $canonicalUrl ?? null;
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="/" aria-label="MediaPitch Store home">
            <img src="https://raw.githubusercontent.com/Kesehet/mediapitch/main/images/media_pitchlogo.jpg" alt="MediaPitch" class="brand-logo">
            <span class="brand-store">STORE</span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="/#categories">Categories</a>
            <a href="/#guides">Best Products</a>
            <a href="/#guides">Buying Guides</a>
            <a href="/blog">Blog</a>
        </nav>
        <form class="header-search" action="/search" method="get" role="search">
            <input type="search" name="q" placeholder="Search products…" aria-label="Search products">
        </form>
    </div>
</header>

<main>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <strong>MediaPitch Store</strong>
            <p>Independent buying guides, comparisons and product discovery.</p>
        </div>
        <div>
            <strong>Affiliate disclosure</strong>
            <p>As an Amazon Associate, MediaPitch may earn from qualifying purchases. Product availability and prices can change on Amazon.</p>
        </div>
    </div>
</footer>
</body>
</html>
