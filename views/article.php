<article>
  <header class="section"><div class="container narrow">
    <?php if(!empty($post['category_name'])):?><div class="eyebrow"><?= e($post['category_name']) ?></div><?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <p class="muted"><?php if(!empty($post['published_at'])):?><?= e(date('j F Y',strtotime((string)$post['published_at']))) ?><?php endif; ?><?php if(!empty($post['author_name'])):?> · <?= e($post['author_name']) ?><?php endif; ?></p>
    <?php if(!empty($post['excerpt'])):?><p class="lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
  </div></header>
  <?php if(!empty($post['featured_image_url'])):?><div class="container narrow"><img class="article-hero" src="<?= e($post['featured_image_url']) ?>" alt="<?= e($post['title']) ?>"></div><?php endif; ?>
  <section class="section"><div class="container narrow prose"><?= $post['body'] ?? '' ?></div></section>
</article>
