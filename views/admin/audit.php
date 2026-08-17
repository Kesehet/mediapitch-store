<section class="panel">
  <div class="panel-head"><div><h2>Admin audit log</h2><p class="muted">Recent administrative changes. Passwords, credentials and access tokens are never recorded here.</p></div><span><?= count($entries) ?> recent events</span></div>
  <?php if(!empty($schemaError)):?><div class="flash error"><?= e($schemaError) ?></div><?php endif;?>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Summary</th><th>Details</th></tr></thead><tbody>
  <?php foreach($entries as $entry): $metadata=json_decode((string)($entry['metadata_json']??''),true); ?>
    <tr>
      <td><small><?= e((string)$entry['created_at']) ?> UTC</small></td>
      <td><strong><?= e($entry['user_name']??'System') ?></strong><?php if(!empty($entry['user_email'])):?><small><?= e($entry['user_email']) ?></small><?php endif;?></td>
      <td><code><?= e($entry['action']) ?></code></td>
      <td><?= e($entry['entity_type']) ?><?php if(!empty($entry['entity_id'])):?><small>#<?= (int)$entry['entity_id'] ?></small><?php endif;?></td>
      <td><?= e($entry['summary']??'—') ?></td>
      <td><?php if(is_array($metadata)&&$metadata):?><details><summary>View</summary><pre style="white-space:pre-wrap;max-width:420px"><?= e(json_encode($metadata,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) ?></pre></details><?php else:?>—<?php endif;?></td>
    </tr>
  <?php endforeach;?>
  <?php if(!$entries):?><tr><td colspan="6" class="empty">No audit events yet.</td></tr><?php endif;?>
  </tbody></table></div>
</section>
