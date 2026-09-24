<?php use MediaPitch\Core\Csrf; ?>
<?php
$baseQuery=[
  'q'=>$query,
  'presence'=>$presence,
  'status'=>$status,
  'validation'=>$validation,
];
?>
<nav class="email-tabs" aria-label="Email Center sections">
  <a class="email-tab-link" href="<?= e(url('admin/sender').'?tab=dashboard') ?>">Dashboard</a>
  <a class="email-tab-link" href="<?= e(url('admin/sender').'?tab=campaigns') ?>">Campaigns</a>
  <a class="email-tab-link" href="<?= e(url('admin/sender').'?tab=templates') ?>">Templates</a>
  <a class="email-tab-link" href="<?= e(url('admin/sender').'?tab=queue') ?>">Send Queue</a>
  <a class="email-tab-link" href="<?= e(url('admin/sender').'?tab=history') ?>">History</a>
  <a class="email-tab-link" href="<?= e(url('admin/sender').'?tab=settings') ?>">Settings</a>
  <a class="email-tab-link is-active" href="<?= e(url('admin/newsletter')) ?>">Subscribers</a>
</nav>

<section class="admin-card" style="margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px">
    <div>
      <h2 style="margin:0 0 6px">Merged subscriber audience</h2>
      <p class="muted" style="margin:0;max-width:820px">One row per unique email, merged live from the MediaPitch newsletter database and Sender. The two systems remain separate; this view does not silently import, delete or re-subscribe anyone.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="button secondary" href="<?= e(url('admin/newsletter').'?'.http_build_query($baseQuery)) ?>">Refresh from Sender</a>
      <a class="button secondary" href="<?= e(url('admin/newsletter').'?'.http_build_query(array_merge($baseQuery,['export'=>'csv']))) ?>">Export merged CSV</a>
    </div>
  </div>

  <?php if($senderError): ?>
    <div class="flash error" style="margin-bottom:18px">Sender subscribers could not be loaded, so these totals currently reflect MediaPitch records only. <?= e($senderError) ?></div>
  <?php elseif($senderTruncated): ?>
    <div class="flash error" style="margin-bottom:18px">Sender reports <?= number_format((int)$senderReportedTotal) ?> subscribers, but the safety fetch limit was reached. The merged unique total below is therefore incomplete.</div>
  <?php endif; ?>

  <div class="admin-grid stats-grid" style="margin-bottom:18px">
    <div class="stat-card"><span>Unique people</span><strong><?= number_format((int)($stats['unique_total']??0)) ?></strong><small>deduplicated by email</small></div>
    <div class="stat-card"><span>In both systems</span><strong><?= number_format((int)($stats['both']??0)) ?></strong><small>same email in MediaPitch + Sender</small></div>
    <div class="stat-card"><span>MediaPitch only</span><strong><?= number_format((int)($stats['local_only']??0)) ?></strong><small><?= number_format((int)($stats['local_total']??0)) ?> local records total</small></div>
    <div class="stat-card"><span>Sender only</span><strong><?= number_format((int)($stats['sender_only']??0)) ?></strong><small><?= number_format((int)($stats['sender_total']??0)) ?> Sender records total</small></div>
    <div class="stat-card"><span>Effectively active</span><strong><?= number_format((int)($stats['effective_active']??0)) ?></strong><small>after respecting either system's suppression</small></div>
    <div class="stat-card"><span>Campaign eligible</span><strong><?= number_format((int)($stats['campaign_eligible']??0)) ?></strong><small>local active + clean, not suppressed by Sender</small></div>
  </div>

  <div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
    <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px"><strong><?= number_format((int)($stats['effective_unsubscribed']??0)) ?></strong><div class="muted">Unsubscribed</div></div>
    <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px"><strong><?= number_format((int)($stats['effective_bounced']??0)) ?></strong><div class="muted">Bounced</div></div>
    <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px"><strong><?= number_format((int)($stats['status_conflicts']??0)) ?></strong><div class="muted">Status conflicts between systems</div></div>
  </div>
</section>

<section class="admin-card">
  <form method="get" action="<?= e(url('admin/newsletter')) ?>" class="admin-filter-row" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:18px">
    <label style="flex:1;min-width:220px">Search
      <input type="search" name="q" value="<?= e($query) ?>" placeholder="Email, name or source">
    </label>
    <label>Presence
      <select name="presence">
        <option value="all">Everywhere</option>
        <option value="both" <?= $presence==='both'?'selected':'' ?>>In both</option>
        <option value="local" <?= $presence==='local'?'selected':'' ?>>MediaPitch only</option>
        <option value="sender" <?= $presence==='sender'?'selected':'' ?>>Sender only</option>
      </select>
    </label>
    <label>Effective status
      <select name="status">
        <option value="all">All</option>
        <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="unsubscribed" <?= $status==='unsubscribed'?'selected':'' ?>>Unsubscribed</option>
        <option value="bounced" <?= $status==='bounced'?'selected':'' ?>>Bounced</option>
        <option value="suppressed" <?= $status==='suppressed'?'selected':'' ?>>Suppressed</option>
        <option value="unknown" <?= $status==='unknown'?'selected':'' ?>>Unknown</option>
      </select>
    </label>
    <label>Local validation
      <select name="validation">
        <option value="all">All</option>
        <option value="clean" <?= $validation==='clean'?'selected':'' ?>>Clean</option>
        <option value="risky" <?= $validation==='risky'?'selected':'' ?>>Risky</option>
        <option value="invalid" <?= $validation==='invalid'?'selected':'' ?>>Invalid</option>
        <option value="unknown" <?= $validation==='unknown'?'selected':'' ?>>Unknown</option>
        <option value="not_checked" <?= $validation==='not_checked'?'selected':'' ?>>Not checked / Sender only</option>
      </select>
    </label>
    <button class="button" type="submit">Filter</button>
    <a class="button secondary" href="<?= e(url('admin/newsletter')) ?>">Clear</a>
  </form>

  <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <div class="muted">Showing <?= number_format(count($subscribers)) ?> of <?= number_format((int)$filteredTotal) ?> matching unique subscriber(s).</div>
    <?php if($totalPages>1): ?><div class="muted">Page <?= (int)$page ?> of <?= (int)$totalPages ?></div><?php endif; ?>
  </div>

  <?php if(empty($subscribers)): ?>
    <div class="empty-state">No merged subscribers match these filters.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>Subscriber</th><th>Presence</th><th>Status</th><th>Validation</th><th>Dates</th><th>Local controls</th></tr></thead>
        <tbody>
        <?php foreach($subscribers as $subscriber):
          $validationStatus=(string)($subscriber['validation_status']??'');
          $validationReason=(string)($subscriber['validation_reason']??'');
          $presenceValue=(string)($subscriber['presence']??'');
          $localStatus=(string)($subscriber['local_status']??'');
          $senderStatus=(string)($subscriber['sender_status']??'');
        ?>
          <tr>
            <td>
              <strong><?= e((string)$subscriber['email']) ?></strong>
              <?php if(!empty($subscriber['display_name'])):?><div class="muted"><?= e((string)$subscriber['display_name']) ?></div><?php endif; ?>
              <?php if(!empty($subscriber['local_source'])):?><div class="muted" style="font-size:11px">Local source: <?= e((string)$subscriber['local_source']) ?></div><?php endif; ?>
            </td>
            <td>
              <?php if($presenceValue==='both'): ?><strong>MediaPitch + Sender</strong>
              <?php elseif($presenceValue==='local'): ?><strong>MediaPitch only</strong>
              <?php else: ?><strong>Sender only</strong><?php endif; ?>
              <?php if(!empty($subscriber['sender_id'])):?><div class="muted" style="font-size:11px">Sender ID: <?= e((string)$subscriber['sender_id']) ?></div><?php endif; ?>
            </td>
            <td>
              <strong><?= e(ucfirst((string)($subscriber['effective_status']??'unknown'))) ?></strong>
              <div class="muted" style="font-size:12px">
                Local: <?= e($localStatus!==''?ucfirst($localStatus):'—') ?> ·
                Sender: <?= e($senderStatus!==''?ucfirst($senderStatus):'—') ?>
              </div>
              <?php if(!empty($subscriber['status_conflict'])):?><div style="font-size:12px;margin-top:4px"><strong>Status conflict</strong></div><?php endif; ?>
            </td>
            <td>
              <?php if($validationStatus===''): ?>
                <span class="muted"><?= !empty($subscriber['in_local'])?'Not checked':'Sender only' ?></span>
              <?php else: ?>
                <strong><?= e(ucfirst($validationStatus)) ?></strong>
                <?php if($validationReason!==''): ?><div class="muted" style="font-size:12px;max-width:300px;margin-top:3px"><?= e($validationReason) ?></div><?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if(!empty($subscriber['local_subscribed_at'])):?><div><strong>Local:</strong> <?= e((string)$subscriber['local_subscribed_at']) ?></div><?php endif; ?>
              <?php if(!empty($subscriber['sender_created_at'])):?><div><strong>Sender:</strong> <?= e((string)$subscriber['sender_created_at']) ?></div><?php endif; ?>
              <?php if(empty($subscriber['local_subscribed_at'])&&empty($subscriber['sender_created_at'])):?><span class="muted">—</span><?php endif; ?>
            </td>
            <td>
              <?php if((int)($subscriber['local_id']??0)>0): ?>
                <form method="post" action="<?= e(url('admin/newsletter/action')) ?>" style="display:flex;gap:6px;white-space:nowrap;flex-wrap:wrap">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= (int)$subscriber['local_id'] ?>">
                  <button class="button secondary" name="action" value="revalidate">Revalidate</button>
                  <?php if($localStatus==='active'): ?>
                    <button class="button secondary" name="action" value="unsubscribe">Local unsubscribe</button>
                  <?php else: ?>
                    <button class="button" name="action" value="activate">Local reactivate</button>
                  <?php endif; ?>
                  <button class="button secondary" name="action" value="delete" onclick="return confirm('Delete only the MediaPitch copy of this subscriber? The Sender record, if any, will not be changed.')">Delete local</button>
                </form>
              <?php else: ?>
                <span class="muted">Sender-only record · read-only here</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if($totalPages>1): ?>
    <div style="display:flex;justify-content:flex-end;gap:8px;align-items:center;margin-top:16px;flex-wrap:wrap">
      <?php if($page>1): ?><a class="button secondary" href="<?= e(url('admin/newsletter').'?'.http_build_query(array_merge($baseQuery,['page'=>$page-1]))) ?>">← Previous</a><?php endif; ?>
      <?php if($page<$totalPages): ?><a class="button secondary" href="<?= e(url('admin/newsletter').'?'.http_build_query(array_merge($baseQuery,['page'=>$page+1]))) ?>">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</section>
