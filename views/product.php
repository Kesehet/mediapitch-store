<?php
$features = !empty($product['features_json']) ? json_decode((string) $product['features_json'], true) : [];
$pros = !empty($product['pros_json']) ? json_decode((string) $product['pros_json'], true) : [];
$cons = !empty($product['cons_json']) ? json_decode((string) $product['cons_json'], true) : [];
$gallery = !empty($product['gallery_json']) ? json_decode((string) $product['gallery_json'], true) : [];
if(!is_array($gallery))$gallery=[];
$name = $product['display_title'] ?: $product['title'];
$specifications = $product['specifications'] ?? [];
$specValue = static function (array $spec): string {
    return match ($spec['data_type']) {
        'number' => $spec['value_number'] !== null ? rtrim(rtrim((string)$spec['value_number'], '0'), '.') : '',
        'boolean' => $spec['value_boolean'] === null ? '' : ((int)$spec['value_boolean'] === 1 ? 'Yes' : 'No'),
        default => (string)($spec['value_text'] ?? ''),
    };
};
$productSchema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$name,'description'=>(string)($product['short_description'] ?? '')];
$schemaImages=[];
if(!empty($product['main_image_url']))$schemaImages[]=$product['main_image_url'];
foreach($gallery as $image){if(is_string($image)&&$image!=='')$schemaImages[]=$image;}
if($schemaImages)$productSchema['image']=array_values(array_unique($schemaImages));
if(!empty($product['brand_name'])) $productSchema['brand']=['@type'=>'Brand','name'=>$product['brand_name']];
if(!empty($product['asin'])) $productSchema['sku']=$product['asin'];
$breadcrumbSchema=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
    ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>url()],
]];
if(!empty($product['category_name'])&&!empty($product['category_slug'])) $breadcrumbSchema['itemListElement'][]=['@type'=>'ListItem','position'=>2,'name'=>$product['category_name'],'item'=>url('category/'.$product['category_slug'])];
$breadcrumbSchema['itemListElement'][]=['@type'=>'ListItem','position'=>count($breadcrumbSchema['itemListElement'])+1,'name'=>$name,'item'=>url('product/'.$product['slug'])];
?>
<script type="application/ld+json"><?= json_encode($productSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<section class="section">
    <div class="container"><nav class="muted" aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a><?php if(!empty($product['category_name'])&&!empty($product['category_slug'])):?> · <a href="<?= e(url('category/'.$product['category_slug'])) ?>"><?= e($product['category_name']) ?></a><?php endif;?> · <?= e($name) ?></nav></div>
    <div class="container product-detail-grid">
        <div>
            <div class="product-detail-image" id="product-main-image">
                <?php if (!empty($product['main_image_url'])): ?>
                    <img src="<?= e($product['main_image_url']) ?>" alt="<?= e($name) ?>">
                <?php else: ?>
                    <div class="image-placeholder">Product image</div>
                <?php endif; ?>
            </div>
            <?php if($gallery):?><div class="product-gallery" aria-label="Product gallery">
                <?php if(!empty($product['main_image_url'])):?><button type="button" class="product-gallery-thumb active" data-image="<?= e($product['main_image_url']) ?>"><img src="<?= e($product['main_image_url']) ?>" alt="<?= e($name) ?> main image"></button><?php endif;?>
                <?php foreach($gallery as $i=>$image): if(!is_string($image)||$image==='')continue;?><button type="button" class="product-gallery-thumb" data-image="<?= e($image) ?>"><img src="<?= e($image) ?>" alt="<?= e($name) ?> image <?= (int)$i+1 ?>" loading="lazy"></button><?php endforeach;?>
            </div><?php endif;?>
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

<?php if (!empty($specifications)): ?>
<section class="section section-soft">
  <div class="container narrow">
    <h2>Specifications</h2>
    <div class="spec-table-wrap"><table class="spec-table"><tbody>
      <?php foreach ($specifications as $spec): $value=$specValue($spec); if ($value==='') continue; ?>
        <tr><th><?= e($spec['name']) ?></th><td><?= e($value) ?><?php if (!empty($spec['unit'])): ?> <?= e($spec['unit']) ?><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>
<?php endif; ?>

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
<section class="section section-soft"><div class="container narrow prose"><h2>Our review</h2><p><?= nl2br(e((string)$product['full_description'])) ?></p></div></section>
<?php endif; ?>

<?php if($gallery):?><script>(function(){const box=document.getElementById('product-main-image');const thumbs=[...document.querySelectorAll('.product-gallery-thumb')];if(!box||!thumbs.length)return;thumbs.forEach(btn=>btn.addEventListener('click',()=>{const src=btn.dataset.image;if(!src)return;let img=box.querySelector('img');if(!img){box.innerHTML='<img alt="">';img=box.querySelector('img');}img.src=src;img.alt=<?= json_encode($name,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;thumbs.forEach(t=>t.classList.remove('active'));btn.classList.add('active');}));})();</script><?php endif;?>
