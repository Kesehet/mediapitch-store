<?php
$articleSchema=['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$post['title'],'description'=>(string)($post['excerpt'] ?? '')];
if(!empty($post['featured_image_url'])) $articleSchema['image']=$post['featured_image_url'];
if(!empty($post['published_at'])) $articleSchema['datePublished']=date(DATE_ATOM,strtotime((string)$post['published_at']));
if(!empty($post['updated_at'])) $articleSchema['dateModified']=date(DATE_ATOM,strtotime((string)$post['updated_at']));
if(!empty($post['author_name'])) $articleSchema['author']=['@type'=>'Person','name'=>$post['author_name']];
$articleSchema['mainEntityOfPage']=url('blog/'.$post['slug']);
$breadcrumbSchema=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
  ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>url()],
  ['@type'=>'ListItem','position'=>2,'name'=>'Blog','item'=>url('blog')],
  ['@type'=>'ListItem','position'=>3,'name'=>$post['title'],'item'=>url('blog/'.$post['slug'])],
]];
$relatedItems=(new \MediaPitch\Repositories\RelatedContentRepository())->forContent((int)$post['id'],!empty($post['category_id'])?(int)$post['category_id']:null);
?>
<script type="application/ld+json"><?= json_encode($articleSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<article>
  <header class="section"><div class="container narrow">
    <nav class="muted" aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a> · <a href="<?= e(url('blog')) ?>">Blog</a> · <?= e($post['title']) ?></nav>
    <?php if(!empty($post['category_name'])):?><div class="eyebrow"><?= e($post['category_name']) ?></div><?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <p class="muted"><?php if(!empty($post['published_at'])):?><?= e(date('j F Y',strtotime((string)$post['published_at']))) ?><?php endif; ?><?php if(!empty($post['author_name'])):?> · <?= e($post['author_name']) ?><?php endif; ?></p>
    <?php if(!empty($post['excerpt'])):?><p class="lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
  </div></header>
  <?php if(!empty($post['featured_image_url'])):?><div class="container narrow"><img class="article-hero" src="<?= e($post['featured_image_url']) ?>" alt="<?= e($post['title']) ?>"></div><?php endif; ?>
  <section class="section"><div class="container narrow prose"><?= safe_html($post['body'] ?? '') ?></div></section>
</article>
<?php require __DIR__.'/partials/related-content.php'; ?>
