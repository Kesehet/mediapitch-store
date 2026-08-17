<?php $products=$comparison['products']??[]; $specs=$comparison['specifications']??[]; $relatedItems=(new \MediaPitch\Repositories\RelatedContentRepository())->forContent((int)$comparison['id'],!empty($comparison['category_id'])?(int)$comparison['category_id']:null); ?>
<section class="section"><div class="container narrow">
<nav class="muted" aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a> · <a href="<?= e(url('comparisons')) ?>">Comparisons</a> · <?= e($comparison['title']) ?></nav>
<?php if(!empty($comparison['category_name'])):?><div class="eyebrow"><a href="<?= e(url('category/'.$comparison['category_slug'])) ?>"><?= e($comparison['category_name']) ?></a></div><?php endif;?>
<h1><?= e($comparison['title']) ?></h1>
<?php if(!empty($comparison['excerpt'])):?><p class="lead"><?= e($comparison['excerpt']) ?></p><?php endif;?>
</div></section>

<section class="section section-soft"><div class="container"><p class="comparison-scroll-hint">Swipe sideways to compare every product →</p><div class="table-wrap"><table class="comparison-table"><thead><tr><th>Feature</th><?php foreach($products as $product):?><th><a href="<?= e(url('product/'.$product['slug'])) ?>"><?= e($product['display_title']?:$product['title']) ?></a></th><?php endforeach;?></tr></thead><tbody>
<tr><th>Brand</th><?php foreach($products as $product):?><td><?= e($product['brand_name']??'—') ?></td><?php endforeach;?></tr>
<tr><th>MediaPitch score</th><?php foreach($products as $product):?><td><?= $product['custom_score']!==null?e((string)$product['custom_score']).'/10':'—' ?></td><?php endforeach;?></tr>
<tr><th>Best for</th><?php foreach($products as $product):?><td><?= e($product['best_for_label']??'—') ?></td><?php endforeach;?></tr>
<tr><th>Last recorded price</th><?php foreach($products as $product): $displayPrice=public_product_price($product);?><td><?= $displayPrice!==null?e(($product['currency']??'INR').' '.number_format($displayPrice,0)):'—' ?></td><?php endforeach;?></tr>
<?php foreach($specs as $spec):?><tr><th><?= e($spec['name']) ?><?= !empty($spec['unit'])?' ('.e($spec['unit']).')':'' ?></th><?php foreach($products as $product):?><td><?= e((string)($spec['values'][(int)$product['id']]??'—')) ?></td><?php endforeach;?></tr><?php endforeach;?>
<tr><th>Buy / check price</th><?php foreach($products as $product):?><td><?php if(!empty($product['affiliate_url'])):?><a class="button button-secondary" href="<?= e(url('go/'.(int)$product['id'].'?content='.(int)$comparison['id'].'&from=comparison')) ?>">Check Price</a><?php else:?>—<?php endif;?></td><?php endforeach;?></tr>
</tbody></table></div></div></section>

<?php if(!empty($comparison['body'])):?><section class="section"><div class="container narrow prose"><h2>Our verdict</h2><?= safe_html($comparison['body']) ?></div></section><?php endif;?>
<section class="section section-soft"><div class="container narrow"><p class="affiliate-note">We may earn a commission from qualifying purchases. Prices and availability are controlled by Amazon and can change.</p><p><a href="<?= e(url('comparisons')) ?>">Browse all comparisons →</a></p></div></section>
<?php require __DIR__.'/partials/related-content.php'; ?>
