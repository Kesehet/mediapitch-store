<?php
$tag=$tag??[];
$items=$items??[];
$tagName=(string)($tag['name']??'Tag');
$schema=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$tagName,'url'=>url('tag/'.($tag['slug']??''))];
?>
<script type="application/ld+json"><?= json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<section class="section section-soft"><div class="container narrow">
<nav class="muted" aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a> · Tags · <?= e($tagName) ?></nav>
<span class="eyebrow">Topic</span><h1><?= e($tagName) ?></h1>
<p class="lead">Articles, buying guides, reviews and comparisons tagged <?= e($tagName) ?>.</p>
</div></section>
<section class="section"><div class="container"><div class="article-grid">
<?php foreach($items as $item):
  $prefix=match($item['type']){'buying_guide'=>'guide','comparison'=>'compare','review'=>'review',default=>'blog'};
  $label=match($item['type']){'buying_guide'=>'Buying Guide','comparison'=>'Comparison','review'=>'Review',default=>'Article'};
?>
<article class="article-card"><div class="card-body"><span class="card-kicker"><?= e($label) ?></span>
<h2><a href="<?= e(url($prefix.'/'.$item['slug'])) ?>"><?= e($item['title']) ?></a></h2>
<?php if(!empty($item['excerpt'])):?><p><?= e($item['excerpt']) ?></p><?php endif;?>
</div></article>
<?php endforeach;?>
<?php if(!$items):?><div class="empty-state"><p>No published content uses this tag yet.</p></div><?php endif;?>
</div></div></section>
