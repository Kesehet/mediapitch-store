<?php
$active=$category['active_filters'] ?? ['brand'=>0,'min_price'=>null,'max_price'=>null,'min_score'=>null,'sort'=>'score','spec'=>[]];
$filterOptions=$category['filter_options'] ?? ['brands'=>[],'specifications'=>[]];
$pagination=$category['pagination'] ?? ['page'=>1,'pages'=>1,'total'=>count($category['products'] ?? [])];
$baseQuery=$_GET;
unset($baseQuery['page']);
$pageUrl=function(int $page) use($baseQuery,$category): string {
    $q=$baseQuery; $q['page']=$page;
    return url('category/' . $category['slug']) . '?' . http_build_query($q);
};
?>
<section class="section">
  <div class="container narrow center">
    <div class="eyebrow">Category</div>
    <h1><?= e($category['name']) ?></h1>
    <?php if(!empty($category['description'])):?><p class="lead"><?= e($category['description']) ?></p><?php endif; ?>
  </div>
</section>

<section class="section section-soft"><div class="container">
  <form class="catalog-filters" method="get" action="<?= e(url('category/' . $category['slug'])) ?>">
    <label>Brand
      <select name="brand"><option value="">All brands</option><?php foreach($filterOptions['brands'] as $brand):?><option value="<?= (int)$brand['id'] ?>" <?= (int)$active['brand']===(int)$brand['id']?'selected':'' ?>><?= e($brand['name']) ?></option><?php endforeach;?></select>
    </label>
    <label>Min price<input type="number" min="0" step="1" name="min_price" value="<?= e($active['min_price']!==null?(string)$active['min_price']:'') ?>"></label>
    <label>Max price<input type="number" min="0" step="1" name="max_price" value="<?= e($active['max_price']!==null?(string)$active['max_price']:'') ?>"></label>
    <label>Minimum score<select name="min_score"><option value="">Any score</option><?php foreach([5,6,7,8,9] as $score):?><option value="<?= $score ?>" <?= (string)$active['min_score']===(string)$score?'selected':'' ?>><?= $score ?>+</option><?php endforeach;?></select></label>
    <label>Sort<select name="sort"><option value="score" <?= $active['sort']==='score'?'selected':'' ?>>Top rated</option><option value="price_asc" <?= $active['sort']==='price_asc'?'selected':'' ?>>Price: low to high</option><option value="price_desc" <?= $active['sort']==='price_desc'?'selected':'' ?>>Price: high to low</option><option value="newest" <?= $active['sort']==='newest'?'selected':'' ?>>Newest</option><option value="title" <?= $active['sort']==='title'?'selected':'' ?>>A–Z</option></select></label>
    <?php foreach($filterOptions['specifications'] as $definition):
      $slug=(string)$definition['slug']; $value=(string)($active['spec'][$slug] ?? ''); $options=json_decode((string)($definition['options_json'] ?? ''),true); if(!is_array($options))$options=[];
    ?>
      <label><?= e($definition['name']) ?><?php if(!empty($definition['unit'])):?> <small>(<?= e($definition['unit']) ?>)</small><?php endif;?>
        <?php if($definition['data_type']==='select'):?><select name="spec[<?= e($slug) ?>]"><option value="">Any</option><?php foreach($options as $option):?><option value="<?= e((string)$option) ?>" <?= $value===(string)$option?'selected':'' ?>><?= e((string)$option) ?></option><?php endforeach;?></select>
        <?php elseif($definition['data_type']==='boolean'):?><select name="spec[<?= e($slug) ?>]"><option value="">Any</option><option value="1" <?= $value==='1'?'selected':'' ?>>Yes</option><option value="0" <?= $value==='0'?'selected':'' ?>>No</option></select>
        <?php elseif($definition['data_type']==='number'):?><input type="number" step="any" name="spec[<?= e($slug) ?>]" value="<?= e($value) ?>">
        <?php else:?><input name="spec[<?= e($slug) ?>]" value="<?= e($value) ?>"><?php endif;?>
      </label>
    <?php endforeach;?>
    <div class="filter-actions"><button class="button" type="submit">Apply filters</button><a class="button button-secondary" href="<?= e(url('category/' . $category['slug'])) ?>">Clear</a></div>
  </form>
</div></section>

<section class="section"><div class="container">
  <div class="section-head"><div><span class="eyebrow">Products</span><h2>Recommended <?= e($category['name']) ?></h2><p class="muted"><?= (int)$pagination['total'] ?> product<?= (int)$pagination['total']===1?'':'s' ?> found</p></div></div>
  <?php if(!empty($category['products'])): ?>
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
  <?php else:?><div class="empty-state"><h2>No products match these filters.</h2><p>Try clearing one or more filters.</p></div><?php endif;?>

  <?php if((int)$pagination['pages']>1):?><nav class="pagination" aria-label="Product pages">
    <?php if((int)$pagination['page']>1):?><a href="<?= e($pageUrl((int)$pagination['page']-1)) ?>">← Previous</a><?php endif;?>
    <span>Page <?= (int)$pagination['page'] ?> of <?= (int)$pagination['pages'] ?></span>
    <?php if((int)$pagination['page']<(int)$pagination['pages']):?><a href="<?= e($pageUrl((int)$pagination['page']+1)) ?>">Next →</a><?php endif;?>
  </nav><?php endif;?>
</div></section>

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
