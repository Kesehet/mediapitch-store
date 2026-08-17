<section class="section section-soft"><div class="container narrow"><span class="eyebrow">Compare</span><h1>Product comparisons</h1><p class="lead">Side-by-side comparisons built around the specifications, strengths and trade-offs that actually matter.</p></div></section>
<section class="section"><div class="container">
<?php if(empty($comparisons)): ?>
  <div class="empty-state"><h2>No comparisons published yet</h2><p>Published comparisons will appear here automatically.</p></div>
<?php else: ?>
  <div class="article-grid">
    <?php foreach($comparisons as $item): ?>
      <article class="article-card">
        <?php if(!empty($item['featured_image_url'])):?><img src="<?= e($item['featured_image_url']) ?>" alt="<?= e($item['title']) ?>" loading="lazy"><?php endif; ?>
        <div class="card-body">
          <span class="card-kicker"><?= e($item['category_name'] ?: 'Comparison') ?></span>
          <h2><a href="<?= e(url('compare/'.$item['slug'])) ?>"><?= e($item['title']) ?></a></h2>
          <?php if(!empty($item['excerpt'])):?><p><?= e($item['excerpt']) ?></p><?php endif; ?>
          <p class="muted"><?= (int)$item['product_count'] ?> products compared</p>
          <a class="button button-secondary" href="<?= e(url('compare/'.$item['slug'])) ?>">View comparison</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div></section>
