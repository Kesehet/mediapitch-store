<?php
$daily=$report['daily']??[];
$maxDaily=1;
foreach($daily as $row)$maxDaily=max($maxDaily,(int)$row['clicks']);
?>
<section class="panel">
  <div class="panel-head"><div><h2>Affiliate analytics</h2><p>Track outbound Amazon clicks by date, product, content, CTA, rank and campaign.</p></div><a class="secondary-button" href="<?= e(url('admin/analytics/export?from='.$from.'&to='.$to)) ?>">Export CSV</a></div>
  <form method="get" action="<?= e(url('admin/analytics')) ?>" class="form-grid" style="max-width:650px;margin-bottom:1.25rem">
    <label>From<input type="date" name="from" value="<?= e($from) ?>"></label>
    <label>To<input type="date" name="to" value="<?= e($to) ?>"></label>
    <div><button class="primary-button">Apply range</button></div>
  </form>
  <div class="stat-grid"><div class="stat-card"><span>Total affiliate clicks</span><strong><?= (int)($report['total']??0) ?></strong><small><?= e($from) ?> → <?= e($to) ?></small></div></div>
</section>

<section class="panel">
  <div class="panel-head"><div><h2>Daily trend</h2><p>Outbound affiliate clicks per UTC day.</p></div></div>
  <?php if(!$daily):?><p class="empty">No clicks in this date range.</p><?php else:?><div style="display:grid;gap:8px"><?php foreach($daily as $row): $pct=max(2,round(((int)$row['clicks']/$maxDaily)*100));?><div style="display:grid;grid-template-columns:105px 1fr 50px;gap:10px;align-items:center"><small><?= e($row['day']) ?></small><div style="height:10px;background:#eef2f7;border-radius:999px;overflow:hidden"><div style="width:<?= (int)$pct ?>%;height:100%;background:#2563eb"></div></div><strong><?= (int)$row['clicks'] ?></strong></div><?php endforeach;?></div><?php endif;?>
</section>

<div class="two-col">
<section class="panel"><div class="panel-head"><h2>Top products</h2></div><table class="data-table"><thead><tr><th>Product</th><th>Clicks</th></tr></thead><tbody><?php foreach($report['products'] as $row):?><tr><td><a href="<?= e(url('product/'.$row['slug'])) ?>" target="_blank" rel="noopener"><?= e($row['title']) ?></a></td><td><?= (int)$row['clicks'] ?></td></tr><?php endforeach;?><?php if(!$report['products']):?><tr><td colspan="2" class="empty">No data.</td></tr><?php endif;?></tbody></table></section>
<section class="panel"><div class="panel-head"><h2>Top content</h2></div><table class="data-table"><thead><tr><th>Content</th><th>Clicks</th></tr></thead><tbody><?php foreach($report['content'] as $row):?><tr><td><strong><?= e($row['title']) ?></strong><small><?= e(str_replace('_',' ',$row['type'])) ?></small></td><td><?= (int)$row['clicks'] ?></td></tr><?php endforeach;?><?php if(!$report['content']):?><tr><td colspan="2" class="empty">No content-attributed clicks.</td></tr><?php endif;?></tbody></table></section>
</div>

<div class="two-col" style="margin-top:20px">
<section class="panel"><div class="panel-head"><h2>CTA locations</h2></div><table class="data-table"><thead><tr><th>Location</th><th>Clicks</th></tr></thead><tbody><?php foreach($report['cta'] as $row):?><tr><td><?= e($row['label']) ?></td><td><?= (int)$row['clicks'] ?></td></tr><?php endforeach;?></tbody></table></section>
<section class="panel"><div class="panel-head"><h2>Campaigns</h2></div><table class="data-table"><thead><tr><th>Campaign</th><th>Clicks</th></tr></thead><tbody><?php foreach($report['campaigns'] as $row):?><tr><td><?= e($row['label']) ?></td><td><?= (int)$row['clicks'] ?></td></tr><?php endforeach;?></tbody></table></section>
</div>

<section class="panel" style="margin-top:20px"><div class="panel-head"><h2>Guide/comparison rank positions</h2></div><table class="data-table"><thead><tr><th>Rank</th><th>Clicks</th></tr></thead><tbody><?php foreach($report['ranks'] as $row):?><tr><td><?= e($row['label']) ?></td><td><?= (int)$row['clicks'] ?></td></tr><?php endforeach;?></tbody></table></section>
