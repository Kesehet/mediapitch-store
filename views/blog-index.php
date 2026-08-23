<section class="section">
  <div class="container narrow center"><div class="eyebrow">MediaPitch Editorial</div><h1>Buying advice &amp; product insights</h1><p class="lead">Independent buying advice, practical product explainers, comparisons and shopping insights from the MediaPitch editorial team.</p></div>
</section>
<section class="section section-soft"><div class="container">
  <?php if (!$posts): ?><p class="muted center">No articles have been published yet.</p><?php else: ?>
  <div class="article-grid">
    <?php foreach($posts as $post): ?>
      <article class="article-card">
        <?php if(!empty($post['featured_image_url'])):?><a href="<?= e(url('blog/' . $post['slug'])) ?>" aria-label="Read <?= e($post['title']) ?>"><img src="<?= e($post['featured_image_url']) ?>" alt="<?= e($post['title']) ?>" loading="lazy" decoding="async"></a><?php endif; ?>
        <div class="article-card-body">
          <?php if(!empty($post['category_name'])):?><div class="eyebrow"><?= e($post['category_name']) ?></div><?php endif; ?>
          <h2><a href="<?= e(url('blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
          <?php if(!empty($post['excerpt'])):?><p><?= e($post['excerpt']) ?></p><?php endif; ?>
          <p class="muted"><?php if(!empty($post['published_at'])):?><time datetime="<?= e(gmdate('c',strtotime((string)$post['published_at'].' UTC'))) ?>"><?= e(date('j M Y',strtotime((string)$post['published_at']))) ?></time><?php endif; ?><?php if(!empty($post['author_name'])): ?> · <?= e($post['author_name']) ?><?php endif; ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php
    $items=[];
    foreach($posts as $i=>$post){
      $items[]=['@type'=>'ListItem','position'=>$i+1,'url'=>url('blog/'.$post['slug']),'name'=>$post['title']];
    }
    $schema=['@context'=>'https://schema.org','@type'=>'ItemList','name'=>'MediaPitch Blog','itemListElement'=>$items];
  ?>
  <script type="application/ld+json"><?= json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>
</div></section>
