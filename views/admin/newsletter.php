<?php use MediaPitch\Core\Csrf; ?>
<section class="admin-card">
  <div class="admin-grid stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><span>Total subscribers</span><strong><?= number_format((int)($stats['total']??0)) ?></strong></div>
    <div class="stat-card"><span>Active</span><strong><?= number_format((int)($stats['active']??0)) ?></strong></div>
    <div class="stat-card"><span>Unsubscribed</span><strong><?= number_format((int)($stats['unsubscribed']??0)) ?></strong></div>
  </div>
  <form method="get" action="<?= e(url('admin/newsletter')) ?>" class="admin-filter-row" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:18px">
    <label style="flex:1;min-width:220px">Search email<input type="search" name="q" value="<?= e($query) ?>" placeholder="name@example.com"></label>
    <label>Status<select name="status"><option value="all">All</option><option value="active" <?= $status==='active'?'selected':'' ?>>Active</option><option value="unsubscribed" <?= $status==='unsubscribed'?'selected':'' ?>>Unsubscribed</option></select></label>
    <button class="button" type="submit">Filter</button>
    <a class="button secondary" href="<?= e(url('admin/newsletter').'?'.http_build_query(['q'=>$query,'status'=>$status,'export'=>'csv'])) ?>">Export CSV</a>
  </form>
  <?php if(empty($subscribers)): ?><div class="empty-state">No newsletter subscribers found.</div><?php else: ?>
  <div class="table-wrap"><table class="admin-table"><thead><tr><th>Email</th><th>Status</th><th>Source</th><th>Subscribed</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($subscribers as $subscriber): ?><tr>
    <td><strong><?= e($subscriber['email']) ?></strong></td><td><?= e(ucfirst($subscriber['status'])) ?></td><td><?= e($subscriber['source']) ?></td><td><?= e((string)$subscriber['subscribed_at']) ?></td>
    <td><form method="post" action="<?= e(url('admin/newsletter/action')) ?>" style="display:flex;gap:6px;white-space:nowrap"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>"><?php if($subscriber['status']==='active'): ?><button class="button secondary" name="action" value="unsubscribe">Unsubscribe</button><?php else: ?><button class="button" name="action" value="activate">Reactivate</button><?php endif; ?><button class="button secondary" name="action" value="delete" onclick="return confirm('Delete this subscriber permanently?')">Delete</button></form></td>
  </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>