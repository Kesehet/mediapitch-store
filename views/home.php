<?php $siteSettings=$siteSettings??[]; $deals=$deals??[]; $dealsTitle=$dealsTitle??'Deals worth a look'; ?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow">MediaPitch Guides</span>
            <h1>Smarter buying starts here</h1>
            <p>Clear product advice, useful comparisons and practical shopping insights to help you understand what matters before you buy.</p>
            <form class="hero-search" action="/search" method="get" role="search">
                <input type="search" name="q" placeholder="What are you looking to buy?" aria-label="What are you looking to buy?">
                <button type="submit">Search</button>
            </form>
            <div class="search-hints">Try: AC, television, refrigerator, laptop</div>
        </div>
        <div class="hero-card">
            <div class="hero-card-badge">How we help</div>
            <div class="hero-stat"><strong>Compare</strong><span>Specs that matter</span></div>
            <div class="hero-stat"><strong>Rank</strong><span>Best picks by use case</span></div>
            <div class="hero-stat"><strong>Explain</strong><span>Pros, cons and buying advice</span></div>
        </div>
    </div>
</section>

<?php if (($siteSettings['home_categories']??true) && !empty($categories)): ?>
<section class="section" id="categories">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">Browse</span><h2>Popular categories</h2></div></div>
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <a class="category-card" href="<?= e(url('category/' . $category['slug'])) ?>">
                    <span><?= e($category['name']) ?></span>
                    <small><?= e($category['description'] ?: 'Explore recommendations') ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (($siteSettings['home_guides']??true) && !empty($guides)): ?>
<section class="section section-soft" id="guides">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">Editors' picks</span><h2>Featured buying guides</h2></div></div>
        <div class="article-grid">
            <?php foreach ($guides as $guide): ?>
                <article class="article-card">
                    <?php if (!empty($guide['featured_image_url'])): ?><img src="<?= e($guide['featured_image_url']) ?>" alt="" loading="lazy"><?php endif; ?>
                    <div class="card-body">
                        <span class="card-kicker">Buying Guide</span>
                        <h3><a href="/guide/<?= e($guide['slug']) ?>"><?= e($guide['title']) ?></a></h3>
                        <p><?= e($guide['excerpt'] ?? '') ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (($siteSettings['home_products']??true) && !empty($products)): ?>
<section class="section">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">Discover</span><h2>Top products</h2></div></div>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <a class="product-image" href="/product/<?= e($product['slug']) ?>">
                        <?php if (!empty($product['main_image_url'])): ?><img src="<?= e($product['main_image_url']) ?>" alt="<?= e($product['display_title'] ?: $product['title']) ?>" loading="lazy"><?php else: ?><span class="image-placeholder">Product image</span><?php endif; ?>
                    </a>
                    <div class="card-body">
                        <?php if (!empty($product['best_for_label'])): ?><span class="badge"><?= e($product['best_for_label']) ?></span><?php endif; ?>
                        <h3><a href="/product/<?= e($product['slug']) ?>"><?= e($product['display_title'] ?: $product['title']) ?></a></h3>
                        <?php if ($product['custom_score'] !== null): ?><div class="score">MediaPitch score <strong><?= e((string) $product['custom_score']) ?>/10</strong></div><?php endif; ?>
                        <a class="button button-secondary" href="/product/<?= e($product['slug']) ?>">See recommendation</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if(!empty($deals)): ?>
<section class="section section-soft" id="deals">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">Deals</span><h2><?= e($dealsTitle) ?></h2></div></div>
        <div class="product-grid">
            <?php foreach($deals as $product): $displayPrice=public_product_price($product); ?>
                <article class="product-card">
                    <a class="product-image" href="<?= e(url('product/'.$product['slug'])) ?>"><?php if(!empty($product['main_image_url'])):?><img src="<?= e($product['main_image_url']) ?>" alt="<?= e($product['display_title'] ?: $product['title']) ?>" loading="lazy"><?php else:?><span class="image-placeholder">Product image</span><?php endif;?></a>
                    <div class="card-body">
                        <span class="badge">Deal pick</span>
                        <h3><a href="<?= e(url('product/'.$product['slug'])) ?>"><?= e($product['display_title'] ?: $product['title']) ?></a></h3>
                        <?php if($displayPrice!==null):?><p class="price"><?= e(($product['currency']??'INR').' '.number_format($displayPrice,0)) ?></p><?php endif;?>
                        <a class="button button-secondary" href="<?= e(url('product/'.$product['slug'])) ?>">View product</a>
                    </div>
                </article>
            <?php endforeach;?>
        </div>
    </div>
</section>
<?php endif;?>

<?php if (($siteSettings['home_comparisons']??true) && !empty($comparisons)): ?>
<section class="section section-soft" id="comparisons">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">Compare</span><h2>Latest comparisons</h2></div><a href="<?= e(url('comparisons')) ?>">View all</a></div>
        <div class="article-grid">
            <?php foreach($comparisons as $comparison): ?>
                <article class="article-card">
                    <?php if(!empty($comparison['featured_image_url'])):?><img src="<?= e($comparison['featured_image_url']) ?>" alt="<?= e($comparison['title']) ?>" loading="lazy"><?php endif; ?>
                    <div class="card-body">
                        <span class="card-kicker"><?= e($comparison['category_name'] ?: 'Comparison') ?></span>
                        <h3><a href="<?= e(url('compare/'.$comparison['slug'])) ?>"><?= e($comparison['title']) ?></a></h3>
                        <p><?= e($comparison['excerpt'] ?? '') ?></p>
                        <small><?= (int)$comparison['product_count'] ?> products compared</small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (($siteSettings['home_articles']??true) && !empty($articles)): ?>
<section class="section" id="articles">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">Learn</span><h2>Latest articles</h2></div><a href="<?= e(url('blog')) ?>">View all</a></div>
        <div class="article-grid">
            <?php foreach ($articles as $article): ?>
                <article class="article-card"><div class="card-body"><span class="card-kicker">Article</span><h3><a href="<?= e(url('blog/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h3><p><?= e($article['excerpt'] ?? '') ?></p></div></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (empty($categories) && empty($products) && empty($guides) && empty($articles) && empty($comparisons) && empty($deals)): ?>
<section class="section"><div class="container empty-state"><h2>Your storefront is ready for content.</h2><p>Import the database schema, add categories and products, and this homepage will populate automatically.</p></div></section>
<?php endif; ?>
