<?php
$guideSchema=['@context'=>'https://schema.org','@type'=>'Article','headline'=>$guide['title'],'description'=>(string)($guide['excerpt'] ?? ''),'mainEntityOfPage'=>url('guide/'.$guide['slug'])];
if(!empty($guide['featured_image_url'])) $guideSchema['image']=$guide['featured_image_url'];
if(!empty($guide['published_at'])) $guideSchema['datePublished']=date(DATE_ATOM,strtotime((string)$guide['published_at']));
if(!empty($guide['updated_at'])) $guideSchema['dateModified']=date(DATE_ATOM,strtotime((string)$guide['updated_at']));
$breadcrumbSchema=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
  ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>url()],
  ['@type'=>'ListItem','position'=>2,'name'=>'Buying Guides','item'=>url().'#guides'],
  ['@type'=>'ListItem','position'=>3,'name'=>$guide['title'],'item'=>url('guide/'.$guide['slug'])],
]];
$relatedItems=(new \MediaPitch\Repositories\RelatedContentRepository())->forContent((int)$guide['id'],!empty($guide['category_id'])?(int)$guide['category_id']:null);
$guideContent=(new \MediaPitch\Services\GuideContent())->render((string)($guide['body']??''));
?>
<script type="application/ld+json"><?= json_encode($guideSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
<section class="guide-hero section-soft">
    <div class="container narrow">
        <nav class="muted" aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a> · Buying Guides · <?= e($guide['title']) ?></nav>
        <span class="eyebrow">Buying Guide</span>
        <h1><?= e($guide['title']) ?></h1>
        <?php if (!empty($guide['excerpt'])): ?><p class="lead"><?= e($guide['excerpt']) ?></p><?php endif; ?>
    </div>
</section>

<?php if (!empty($guideContent['toc'])): ?>
<section class="section"><div class="container narrow"><nav class="affiliate-panel" aria-label="Table of contents"><strong>In this guide</strong><ol><?php foreach($guideContent['toc'] as $entry):?><li<?= (int)$entry['level']===3?' style="margin-left:1rem"':'' ?>><a href="#<?= e($entry['id']) ?>"><?= e($entry['label']) ?></a></li><?php endforeach;?></ol></nav></div></section>
<?php endif; ?>

<?php if ($guideContent['html']!==''): ?>
<section class="section"><div class="container narrow prose"><?= $guideContent['html'] ?></div></section>
<?php endif; ?>

<?php if (!empty($guide['products'])): ?>
<section class="section section-soft">
    <div class="container narrow ranking-list">
        <?php foreach ($guide['products'] as $item):
            $name = $item['display_title'] ?: $item['title'];
            $score = $item['guide_score'] ?? $item['custom_score'];
            $bestFor = $item['guide_best_for'] ?: $item['best_for_label'];
            $pros = !empty($item['pros_json']) ? json_decode((string) $item['pros_json'], true) : [];
            $cons = !empty($item['cons_json']) ? json_decode((string) $item['cons_json'], true) : [];
        ?>
        <article class="ranking-card">
            <div class="rank-number">#<?= (int) ($item['rank_position'] ?: 0) ?></div>
            <div class="ranking-content">
                <?php if ($bestFor): ?><span class="badge"><?= e((string) $bestFor) ?></span><?php endif; ?>
                <h2><?= e($name) ?></h2>
                <div class="ranking-product">
                    <div class="ranking-image"><?php if ($item['main_image_url']): ?><img src="<?= e($item['main_image_url']) ?>" alt="<?= e($name) ?>" loading="lazy"><?php else: ?><span class="image-placeholder">Product image</span><?php endif; ?></div>
                    <div>
                        <?php if ($score !== null): ?><div class="score">MediaPitch score <strong><?= e((string) $score) ?>/10</strong></div><?php endif; ?>
                        <?php if (!empty($item['recommendation'])): ?><p><?= e($item['recommendation']) ?></p><?php elseif (!empty($item['short_description'])): ?><p><?= e($item['short_description']) ?></p><?php endif; ?>
                        <div class="mini-pros-cons">
                            <?php if ($pros): ?><div><strong>Pros</strong><ul><?php foreach (array_slice($pros, 0, 3) as $pro): ?><li><?= e((string) $pro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                            <?php if ($cons): ?><div><strong>Cons</strong><ul><?php foreach (array_slice($cons, 0, 3) as $con): ?><li><?= e((string) $con) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                        </div>
                        <?php if (!empty($item['affiliate_url'])): ?><a class="button" href="/go/<?= (int) $item['id'] ?>?content=<?= (int) $guide['id'] ?>&rank=<?= (int) $item['rank_position'] ?>&from=guide"><?= e($item['cta_text'] ?: 'Check Price on Amazon') ?></a><?php endif; ?>
                        <a class="text-link" href="/product/<?= e($item['slug']) ?>">Read product details</a>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section"><div class="container narrow affiliate-panel"><strong>Affiliate disclosure</strong><p>MediaPitch may earn from qualifying purchases made through Amazon links. Rankings and editorial recommendations are managed by MediaPitch.</p></div></section>
<?php require __DIR__.'/partials/related-content.php'; ?>
