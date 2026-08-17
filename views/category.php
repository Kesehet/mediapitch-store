<section class="section">
  <div class="container narrow center">
    <div class="eyebrow">Category</div>
    <h1><?= e($category['name']) ?></h1>
    <?php if(!empty($category['description'])):?><p class="lead"><?= e($category['description']) ?></p><?php endif; ?>
  </div>
</section>

<?php if(!empty($category['products'])): ?>
<section class="section section-soft"><div class="container">
  <div class="section-head"><div><span class="eyebrow">Products</span><h2>Recommended <?= e($category['name']) ?></h2></div></div>
  <div class="product-grid">
    <?php foreach($category['products'] as $product): ?>
      <article class="product-card">
        <a class="product-image" href="<?= e(url('product/' . $product['slug'])) ?>"><?php if(!empty($product['main_image_url'])):?><img src="<?= e($product['main_image_url']) ?>" alt="<?= e($product['display_title'] ?: $product['title']) ?>" loading="lazy"><?php else:?><span class="image-placeholder">Product image</span><?php endif;?></a>
        <div class="card-body">
          <?php if(!empty($product['best_for_label'])):?><span class="badge"><?= e($product['best_for_label']) ?></span><?php endif;?>
          <h3><a href="<?= e(url('product/' . $product['slug'])) ?>"><?= e($product['display_title'] ?: $product['title']) ?></a></h3>
          <?php if(!empty($product['brand_name'])):?><p class="muted"><?= e($product['brand_name']) ?></p><?php endif;?>
          <?php if($product['custom_score']!==null):?><div class="score">MediaPitch score <strong><?= e((string)$product['custom_score']) ?>/10</strong></div><?php endif;?>
          <?php if($product['price']!==null):?><p class="price">₹<?= e(number_format((float)$product['price'],0)) ?></p><?php endif;?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if(!empty($category['guides'])): ?>
<section class="section"><div class="container"><div class="section-head"><div><span class="eyebrow">Buying guides</span><h2>Best products in <?= e($category['name']) ?></h2></div></div><div class="article-grid">
<?php foreach($category['guides'] as $guide):?><article class="article-card"><div class="card-body"><span class="card-kicker">Buying Guide</span><h3><a href="<?= e(url('guide/' . $guide['slug'])) ?>"><?= e($guide['title']) ?></a></h3><p><?= e($guide['excerpt'] ?? '') ?></p></div></article><?php endforeach;?>
</div></div></section>
<?php endif; ?>

<?php if(!empty($category['articles'])): ?>
<section class="section section-soft"><div class="container"><div class="section-head"><div><span class="eyebrow">Learn</span><h2>Articles about <?= e($category['name']) ?></h2></div></div><div class="article-grid">
<?php foreach($category['articles'] as $article):?><article class="article-card"><div class="card-body"><span class="card-kicker">Article</span><h3><a href="<?= e(url('blog/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h3><p><?= e($article['excerpt'] ?? '') ?></p></div></article><?php endforeach;?>
</div></div></section>
<?php endif; ?>

<?php if(empty($category['products'])&&empty($category['guides'])&&empty($category['articles'])):?>
<section class="section section-soft"><div class="container empty-state"><h2>Content is coming soon.</h2><p>This category exists, but no products or articles have been published in it yet.</p></div></section>
<?php endif;?>
