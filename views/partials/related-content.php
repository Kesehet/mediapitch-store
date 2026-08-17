<?php
$items=$relatedItems??[];
if(!$items)return;
$routeFor=static function(array $item): string {
    return match((string)($item['type']??'')){
        'buying_guide'=>url('guide/'.$item['slug']),
        'blog'=>url('blog/'.$item['slug']),
        'comparison'=>url('compare/'.$item['slug']),
        'review'=>url('review/'.$item['slug']),
        default=>url(),
    };
};
$labelFor=static function(string $type): string {
    return match($type){
        'buying_guide'=>'Buying Guide',
        'comparison'=>'Comparison',
        'review'=>'Review',
        default=>'Article',
    };
};
?>
<section class="section section-soft"><div class="container">
  <div class="section-head"><div><span class="eyebrow">Keep exploring</span><h2>Related reading</h2></div></div>
  <div class="article-grid">
    <?php foreach($items as $item): $href=$routeFor($item); ?>
      <article class="article-card">
        <?php if(!empty($item['featured_image_url'])):?><img src="<?= e($item['featured_image_url']) ?>" alt="<?= e($item['title']) ?>" loading="lazy"><?php endif;?>
        <div class="card-body">
          <span class="card-kicker"><?= e($labelFor((string)$item['type'])) ?></span>
          <h3><a href="<?= e($href) ?>"><?= e($item['title']) ?></a></h3>
          <?php if(!empty($item['excerpt'])):?><p><?= e($item['excerpt']) ?></p><?php endif;?>
        </div>
      </article>
    <?php endforeach;?>
  </div>
</div></section>
