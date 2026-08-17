<?php
$features = !empty($product['features_json']) ? json_decode((string) $product['features_json'], true) : [];
$pros = !empty($product['pros_json']) ? json_decode((string) $product['pros_json'], true) : [];
$cons = !empty($product['cons_json']) ? json_decode((string) $product['cons_json'], true) : [];
$name = $product['display_title'] ?: $product['title'];
?>
<section class="section">
    <div class="container product-detail-grid">
        <div class="product-detail-image">
            <?php if (!empty($product['main_image_url'])): ?>
                <img src="<?= e($product['main_image_url']) ?>" alt="<?= e($name) ?>">
            <?php else: ?>
                <div class="image-placeholder">Product image</div>
            <?php endif; ?>
        </div>
        <div>
            <?php if (!empty($product['category_name'])): ?><div class="eyebrow"><?= e($product['category_name']) ?></div><?php endif; ?>
            <h1><?= e($name) ?></h1>
            <?php if (!empty($product['brand_name'])): ?><p class="muted">By <?= e($product['brand_name']) ?></p><?php endif; ?>
            <?php if (!empty($product['best_for_label'])): ?><span class="badge"><?= e($product['best_for_label']) ?></span><?php endif; ?>
            <?php if ($product['custom_score'] !== null): ?><div class="detail-score"><strong><?= e((string) $product['custom_score']) ?>/10</strong><span>MediaPitch score</span></div><?php endif; ?>
            <?php if (!empty($product['short_description'])): ?><p class="lead"><?= e($product['short_description']) ?></p><?php endif; ?>
            <?php if (!empty($product['price'])): ?><p class="price">₹<?= e(number_format((float) $product['price'], 0)) ?> <small>last recorded price</small></p><?php endif; ?>
            <?php if (!empty($product['affiliate_url'])): ?><a class="button" href="/go/<?= (int) $product['id'] ?>?from=product">Check Price on Amazon</a><?php endif; ?>
            <p class="affiliate-note">We may earn a commission from qualifying purchases. Amazon controls final price, stock, shipping and returns.</p>
        </div>
    </div>
</section>

<?php if (!empty($features)): ?>
<section class="section section-soft"><div class="container narrow"><h2>Key features</h2><ul class="feature-list"><?php foreach ($features as $feature): ?><li><?= e((string) $feature) ?></li><?php endforeach; ?></ul></div></section>
<?php endif; ?>

<?php if (!empty($pros) || !empty($cons)): ?>
<section class="section"><div class="container pros-cons">
    <div><h2>Pros</h2><ul><?php foreach ($pros as $pro): ?><li><?= e((string) $pro) ?></li><?php endforeach; ?></ul></div>
    <div><h2>Cons</h2><ul><?php foreach ($cons as $con): ?><li><?= e((string) $con) ?></li><?php endforeach; ?></ul></div>
</div></section>
<?php endif; ?>

<?php if (!empty($product['full_description'])): ?>
<section class="section section-soft"><div class="container narrow prose"><h2>Our review</h2><?= $product['full_description'] ?></div></section>
<?php endif; ?>
