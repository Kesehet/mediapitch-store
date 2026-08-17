<?php
$products=$brand['products']??[];
$brandSchema=['@context'=>'https://schema.org','@type'=>'Brand','name'=>$brand['name'],'url'=>url('brand/'.$brand['slug'])];
if(!empty($brand['logo_url']))$brandSchema['logo']=$brand['logo_url'];
$breadcrumbSchema=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
 ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>url()],
 ['@type'=>'ListItem','position'=>2,'name'=>$brand['name'],'item'=>url('brand/'.$brand['slug'])],
]];
?>
<script type="application/ld+json"><?= json_encode($brandSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<section class="section section-soft"><div class="container narrow">
<nav class="muted" aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a> · <?= e($brand['name']) ?></nav>
<div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap"><?php if(!empty($brand['logo_url'])):?><img src="<?= e($brand['logo_url']) ?>" alt="<?= e($brand['name']) ?> logo" style="max-width:120px;max-height:72px;object-fit:contain"><?php endif;?><div><span class="eyebrow">Brand</span><h1><?= e($brand['name']) ?></h1></div></div>
<?php if(!empty($brand['website_url'])):?><p><a class="text-link" style="margin-left:0" href="<?= e($brand['website_url']) ?>" rel="nofollow noopener" target="_blank">Official website ↗</a></p><?php endif;?>
</div></section>
<section class="section"><div class="container">
<div class="section-head"><div><span class="eyebrow">Products</span><h2><?= e($brand['name']) ?> products</h2></div><span class="muted"><?= count($products) ?> listed</span></div>
<?php if(!$products):?><div class="empty-state"><p>No active products are currently listed for this brand.</p></div><?php else:?><div class="product-grid">
<?php foreach($products as $product):$name=$product['display_title']?:$product['title'];$price=public_product_price($product);?><article class="product-card"><a class="product-image" href="<?= e(url('product/'.$product['slug'])) ?>"><?php if(!empty($product['main_image_url'])):?><img src="<?= e($product['main_image_url']) ?>" alt="<?= e($name) ?>" loading="lazy"><?php else:?><span class="image-placeholder">Product image</span><?php endif;?></a><div class="card-body"><?php if(!empty($product['category_name'])):?><div class="eyebrow"><a href="<?= e(url('category/'.$product['category_slug'])) ?>"><?= e($product['category_name']) ?></a></div><?php endif;?><h3><a href="<?= e(url('product/'.$product['slug'])) ?>"><?= e($name) ?></a></h3><?php if($product['custom_score']!==null):?><div class="score">MediaPitch score <strong><?= e((string)$product['custom_score']) ?>/10</strong></div><?php endif;?><?php if(!empty($product['best_for_label'])):?><p class="muted">Best for <?= e($product['best_for_label']) ?></p><?php endif;?><?php if($price!==null):?><p class="price"><?= e(($product['currency']??'INR').' '.number_format($price,0)) ?></p><?php endif;?><a class="text-link" style="margin-left:0" href="<?= e(url('product/'.$product['slug'])) ?>">View product →</a></div></article><?php endforeach;?>
</div><?php endif;?>
</div></section>
