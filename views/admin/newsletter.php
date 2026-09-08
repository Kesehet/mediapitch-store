<?php use MediaPitch\Core\Csrf; ?>
<section class="admin-card">
  <div class="admin-grid stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><span>Total subscribers</span><strong><?= number_format((int)($stats['total']??0)) ?></strong></div>
    <div class="stat-card"><span>Active</span><strong><?= number_format((int)($stats['active']??0)) ?></strong></div>
    <div class="stat-card"><span>Unsubscribed</span><strong><?= number_format((int)($stats['unsubscribed']??0)) ?></strong></div>
    <div class="stat-card"><span>Risky validation</span><strong><?= number_format((int)($stats['risky']??0)) ?></strong></div>
    <div class="stat-card"><span>Unknown / not checked</span><strong><?= number_format((int)($stats['unknown_count']??0)+(int)($stats['not_checked']??0)) ?></strong></div>
  </div>
  <form method="get" action="<?= e(url('admin/newsletter')) ?>" class="admin-filter-row" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:18px">
    <label style="flex:1;min-width:220px">Search email<input type="search" name="q" value="<?= e($query) ?>" placeholder="name@example.com"></label>
    <label>Status<select name="status"><option value="all">All</option><option value="active" <?= $status==='active'?'selected':'' ?>>Active</option><option value="unsubscribed" <?= $status==='unsubscribed'?'selected':'' ?>>Unsubscribed</option></select></label>
    <label>Validation<select name="validation"><option value="all">All</option><option value="clean" <?= $validation==='clean'?'selected':'' ?>>Clean</option><option value="risky" <?= $validation==='risky'?'selected':'' ?>>Risky</option><option value="unknown" <?= $validation==='unknown'?'selected':'' ?>>Unknown</option><option value="not_checked" <?= $validation==='not_checked'?'selected':'' ?>>Not checked</option></select></label>
    <button class="button" type="submit">Filter</button>
    <a class="button secondary" href="<?= e(url('admin/newsletter').'?'.http_build_query(['q'=>$query,'status'=>$status,'validation'=>$validation,'export'=>'csv'])) ?>">Export CSV</a>
  </form>
  <?php if(empty($subscribers)): ?><div class="empty-state">No newsletter subscribers found.</div><?php else: ?>
  <div class="table-wrap"><table class="admin-table"><thead><tr><th>Email</th><th>Status</th><th>Validation</th><th>Source</th><th>Subscribed</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($subscribers as $subscriber): $validationStatus=(string)($subscriber['validation_status']??''); $validationReason=(string)($subscriber['validation_reason']??''); ?><tr>
    <td><strong><?= e($subscriber['email']) ?></strong></td>
    <td><?= e(ucfirst($subscriber['status'])) ?></td>
    <td><?php if($validationStatus===''): ?><span title="Existing record created before validation tracking">Not checked</span><?php else: ?><strong><?= e(ucfirst($validationStatus)) ?></strong><?php if($validationReason!==''): ?><div style="font-size:12px;opacity:.72;max-width:320px;margin-top:3px"><?= e($validationReason) ?></div><?php endif; ?><?php endif; ?></td>
    <td><?= e($subscriber['source']) ?></td><td><?= e((string)$subscriber['subscribed_at']) ?></td>
    <td><form method="post" action="<?= e(url('admin/newsletter/action')) ?>" style="display:flex;gap:6px;white-space:nowrap"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>"><?php if($subscriber['status']==='active'): ?><button class="button secondary" name="action" value="unsubscribe">Unsubscribe</button><?php else: ?><button class="button" name="action" value="activate">Reactivate</button><?php endif; ?><button class="button secondary" name="action" value="delete" onclick="return confirm('Delete this subscriber permanently?')">Delete</button></form></td>
  </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>