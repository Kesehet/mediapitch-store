<section class="section">
  <div class="container narrow center"><div class="eyebrow">MediaPitch Editorial</div><h1>Buying advice & product insights</h1><p class="lead">Practical guides, explainers and shopping advice to help you make better buying decisions.</p></div>
</section>
<section class="section section-soft"><div class="container">
  <?php if (!$posts): ?><p class="muted center">No articles have been published yet.</p><?php else: ?>
  <div class="article-grid">
    <?php foreach($posts as $post): ?>
      <article class="article-card">
        <?php if(!empty($post['featured_image_url'])):?><a href="<?= e(url('blog/' . $post['slug'])) ?>"><img src="<?= e($post['featured_image_url']) ?>" alt="<?= e($post['title']) ?>"></a><?php endif; ?>
        <div class="article-card-body">
          <?php if(!empty($post['category_name'])):?><div class="eyebrow"><?= e($post['category_name']) ?></div><?php endif; ?>
          <h2><a href="<?= e(url('blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
          <?php if(!empty($post['excerpt'])):?><p><?= e($post['excerpt']) ?></p><?php endif; ?>
          <p class="muted"><?= !empty($post['published_at'])?e(date('j M Y',strtotime((string)$post['published_at']))):'' ?><?php if(!empty($post['author_name'])): ?> · <?= e($post['author_name']) ?><?php endif; ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div><?php endif; ?>
</div></section>
