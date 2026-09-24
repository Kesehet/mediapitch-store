<?php
use MediaPitch\Core\Csrf;

$tabLabels = [
    'dashboard' => 'Dashboard',
    'templates' => 'Templates',
    'queue' => 'Send Queue',
    'history' => 'History',
    'settings' => 'Settings',
];
$previewContent = '';
if (is_array($selectedTemplate)) {
    $previewContent = (string)($selectedTemplate['content'] ?? $selectedTemplate['html'] ?? '');
}
$remainingToday = (int)($stats['remaining_today'] ?? 0);
$dailyLimit = (int)($stats['daily_limit'] ?? 50);
$batchSize = (int)($senderSettings['batch_size'] ?? 50);
?>
<section class="admin-card" style="margin-bottom:18px">
  <div style="display:flex;gap:16px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap">
    <div>
      <h2 style="margin:0 0 6px">Sender Email Center</h2>
      <p class="muted" style="margin:0;max-width:780px">
        Design transactional templates in Sender, queue recipients here, and let MediaPitch validate every address with the list cleaner before delivery.
        Only addresses returning <strong>clean</strong> are sent.
      </p>
    </div>
    <div style="text-align:right">
      <div><strong>Sender:</strong> <?= $providerConfigured && !$providerError ? 'Connected' : ($providerConfigured ? 'Connection error' : 'Not configured') ?></div>
      <div class="muted" style="font-size:13px">Daily cap: <?= number_format($dailyLimit) ?> · Remaining today: <?= number_format($remainingToday) ?></div>
    </div>
  </div>
</section>

<nav class="admin-card" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;padding:12px">
  <?php foreach($tabLabels as $tabKey=>$tabLabel): ?>
    <a class="button <?= $tab===$tabKey?'':'secondary' ?>" href="<?= e(url('admin/sender').'?tab='.$tabKey) ?>"><?= e($tabLabel) ?></a>
  <?php endforeach; ?>
  <a class="button secondary" href="<?= e(url('admin/newsletter')) ?>">Newsletter Subscribers</a>
</nav>

<?php if($providerError): ?>
  <div class="flash error" style="margin-bottom:18px"><?= e($providerError) ?></div>
<?php endif; ?>

<?php if($tab==='dashboard'): ?>
<section class="admin-card">
  <div class="admin-grid stats-grid" style="margin-bottom:22px">
    <div class="stat-card"><span>Sent today</span><strong><?= number_format((int)($stats['sent_today']??0)) ?></strong></div>
    <div class="stat-card"><span>Remaining today</span><strong><?= number_format($remainingToday) ?></strong></div>
    <div class="stat-card"><span>Queued</span><strong><?= number_format((int)($stats['queued']??0)) ?></strong></div>
    <div class="stat-card"><span>Blocked by cleaner</span><strong><?= number_format((int)($stats['blocked']??0)) ?></strong></div>
    <div class="stat-card"><span>Failed</span><strong><?= number_format((int)($stats['failed']??0)) ?></strong></div>
    <div class="stat-card"><span>Total sent</span><strong><?= number_format((int)($stats['sent_total']??0)) ?></strong></div>
  </div>

  <div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:18px">
      <h3 style="margin-top:0">1. Build in Sender</h3>
      <p class="muted">Create or edit the email under Sender → Transactional Emails → Templates. MediaPitch reads those templates through the API.</p>
      <a class="button" href="<?= e(url('admin/sender').'?tab=templates') ?>">View Sender templates</a>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:18px">
      <h3 style="margin-top:0">2. Queue recipients</h3>
      <p class="muted">Paste email addresses or queue existing clean newsletter subscribers. Duplicate recipient/template combinations are skipped.</p>
      <a class="button" href="<?= e(url('admin/sender').'?tab=queue') ?>">Open send queue</a>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:18px">
      <h3 style="margin-top:0">3. Validate and send</h3>
      <p class="muted">The worker re-checks each address immediately before sending. Risky, invalid, and unresolved addresses never reach Sender.</p>
      <form method="post" action="<?= e(url('admin/sender/action')) ?>">
        <?= Csrf::field() ?><input type="hidden" name="action" value="process"><input type="hidden" name="tab" value="dashboard">
        <input type="hidden" name="limit" value="<?= max(1,min(50,$remainingToday ?: 50)) ?>">
        <button class="button" type="submit" <?= !$providerConfigured||$remainingToday<1?'disabled':'' ?>>Process queue now</button>
      </form>
    </div>
  </div>
</section>

<?php elseif($tab==='templates'): ?>
<section class="admin-card">
  <div style="display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:18px">
    <div>
      <h2 style="margin:0 0 4px">Sender templates</h2>
      <p class="muted" style="margin:0">These are pulled live from Sender's transactional-template API.</p>
    </div>
    <span class="muted"><?= number_format(count($templates)) ?> template(s)</span>
  </div>

  <?php if(!$providerConfigured): ?>
    <div class="empty-state">Set <code>SENDER_API_TOKEN</code> in the server environment, then return here.</div>
  <?php elseif(empty($templates)): ?>
    <div class="empty-state">No Sender transactional templates were returned. Create one in Sender and refresh this page.</div>
  <?php else: ?>
    <div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px">
      <?php foreach($templates as $template): $id=(string)($template['id']??''); ?>
        <article style="border:1px solid #e5e7eb;border-radius:12px;padding:16px">
          <div class="muted" style="font-size:12px;margin-bottom:5px"><?= e((string)($template['editor']??'template')) ?> · <?= e($id) ?></div>
          <h3 style="margin:0 0 6px"><?= e((string)($template['title']??'Untitled template')) ?></h3>
          <p style="margin:0 0 12px"><?= e((string)($template['subject']??'')) ?></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="button secondary" href="<?= e(url('admin/sender').'?'.http_build_query(['tab'=>'templates','template'=>$id])) ?>">Preview</a>
            <a class="button" href="<?= e(url('admin/sender').'?'.http_build_query(['tab'=>'queue','template'=>$id])) ?>">Use template</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if($selectedTemplate): ?>
<section class="admin-card" style="margin-top:18px">
  <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap">
    <div>
      <div class="muted" style="font-size:12px"><?= e((string)($selectedTemplate['id']??'')) ?></div>
      <h2 style="margin:4px 0"><?= e((string)($selectedTemplate['title']??'Template preview')) ?></h2>
      <p style="margin:0"><strong>Subject:</strong> <?= e((string)($selectedTemplate['subject']??'')) ?></p>
    </div>
    <a class="button" href="<?= e(url('admin/sender').'?'.http_build_query(['tab'=>'queue','template'=>(string)($selectedTemplate['id']??'')])) ?>">Queue recipients</a>
  </div>
  <?php if($previewContent!==''): ?>
    <?php if(str_contains($previewContent,'<')): ?>
      <iframe sandbox title="Sender template preview" srcdoc="<?= e($previewContent) ?>" style="width:100%;height:600px;border:1px solid #e5e7eb;border-radius:10px;margin-top:16px;background:white"></iframe>
    <?php else: ?>
      <pre style="white-space:pre-wrap;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-top:16px"><?= e($previewContent) ?></pre>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty-state" style="margin-top:16px">Sender did not expose preview content for this template, but it can still be queued and sent by template ID.</div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php elseif($tab==='queue'): ?>
<?php
$requestedTemplate=(string)($_GET['template']??'');
?>
<section class="admin-card" style="margin-bottom:18px">
  <h2 style="margin-top:0">Queue new recipients</h2>
  <p class="muted">Accepted formats: one email per line, <code>email,name</code>, <code>name,email</code>, or <code>Name &lt;email&gt;</code>. Addresses are validated again immediately before sending.</p>
  <form method="post" action="<?= e(url('admin/sender/action')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="queue_manual"><input type="hidden" name="tab" value="queue">
    <div class="admin-grid" style="grid-template-columns:minmax(260px,1fr) minmax(260px,1fr);gap:16px">
      <label>Sender template
        <select name="template_id" required>
          <option value="">Choose template</option>
          <?php foreach($templates as $template): $id=(string)($template['id']??''); ?>
            <option value="<?= e($id) ?>" <?= $requestedTemplate===$id?'selected':'' ?>><?= e((string)($template['title']??$template['subject']??$id)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Shared template variables (optional JSON)
        <textarea name="variables_json" rows="4" placeholder='{"product":"Air Purifier","cta_url":"https://store.mediapitch.in/..."}'></textarea>
      </label>
    </div>
    <label style="display:block;margin-top:14px">Recipients
      <textarea name="recipients" rows="10" required placeholder="person@example.com&#10;Jane Doe &lt;jane@example.com&gt;&#10;other@example.com,Other Person"></textarea>
    </label>
    <label style="display:flex;gap:8px;align-items:flex-start;margin:14px 0">
      <input type="checkbox" name="consent_confirmed" value="1" required style="width:auto;margin-top:3px">
      <span>I confirm these recipients are permitted to receive this email. MediaPitch will still block every address that does not pass the list cleaner as <strong>clean</strong>.</span>
    </label>
    <button class="button" type="submit" <?= empty($templates)?'disabled':'' ?>>Add to queue</button>
  </form>
</section>

<section class="admin-card" style="margin-bottom:18px">
  <h2 style="margin-top:0">Queue existing newsletter subscribers</h2>
  <p class="muted">This selects currently active subscribers whose latest stored validation status is clean. The worker revalidates every address before the actual send.</p>
  <form method="post" action="<?= e(url('admin/sender/action')) ?>" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="queue_subscribers"><input type="hidden" name="tab" value="queue">
    <label style="min-width:280px;flex:1">Sender template
      <select name="template_id" required>
        <option value="">Choose template</option>
        <?php foreach($templates as $template): $id=(string)($template['id']??''); ?>
          <option value="<?= e($id) ?>" <?= $requestedTemplate===$id?'selected':'' ?>><?= e((string)($template['title']??$template['subject']??$id)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="display:flex;gap:8px;align-items:center;flex:2;min-width:300px">
      <input type="checkbox" name="consent_confirmed" value="1" required style="width:auto">
      <span>Confirm these active newsletter subscribers may receive this email.</span>
    </label>
    <button class="button" type="submit" <?= empty($templates)?'disabled':'' ?>>Queue clean subscribers</button>
  </form>
  <p class="muted" style="margin-bottom:0;margin-top:12px">
    Current subscriber records: <?= number_format((int)($newsletterStats['total']??0)) ?> total · <?= number_format((int)($newsletterStats['active']??0)) ?> active.
  </p>
</section>

<section class="admin-card">
  <div style="display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <h2 style="margin:0 0 4px">Send queue</h2>
      <p class="muted" style="margin:0">The daily limit is enforced by successful sends, not by how many addresses are queued.</p>
    </div>
    <form method="post" action="<?= e(url('admin/sender/action')) ?>" style="display:flex;gap:8px;align-items:end">
      <?= Csrf::field() ?><input type="hidden" name="action" value="process"><input type="hidden" name="tab" value="queue">
      <label>Process up to<input type="number" name="limit" min="1" max="100" value="<?= max(1,min(50,$remainingToday ?: 50)) ?>" style="width:90px"></label>
      <button class="button" type="submit" <?= !$providerConfigured||$remainingToday<1?'disabled':'' ?>>Process now</button>
    </form>
  </div>

  <form method="get" action="<?= e(url('admin/sender')) ?>" style="display:flex;gap:8px;align-items:end;margin-bottom:14px">
    <input type="hidden" name="tab" value="queue">
    <label>Status<select name="status">
      <?php foreach(['all','queued','processing','sent','blocked','failed'] as $s): ?><option value="<?= e($s) ?>" <?= $queueStatus===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
    </select></label>
    <button class="button secondary" type="submit">Filter</button>
  </form>

  <?php if(empty($queueRows)): ?><div class="empty-state">No queue items found.</div><?php else: ?>
    <div class="table-wrap"><table class="admin-table">
      <thead><tr><th>Recipient</th><th>Template</th><th>Status</th><th>Cleaner</th><th>Attempts</th><th>Created</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($queueRows as $row): ?>
        <tr>
          <td><strong><?= e((string)$row['recipient_email']) ?></strong><?php if(!empty($row['recipient_name'])):?><div class="muted"><?= e((string)$row['recipient_name']) ?></div><?php endif;?></td>
          <td><?= e((string)($row['template_title']?:$row['template_id'])) ?><div class="muted" style="font-size:11px"><?= e((string)$row['template_id']) ?></div></td>
          <td><strong><?= e(ucfirst((string)$row['status'])) ?></strong><?php if(!empty($row['last_error'])):?><div class="muted" style="font-size:12px;max-width:320px"><?= e((string)$row['last_error']) ?></div><?php endif;?></td>
          <td><?= e($row['validation_status']?ucfirst((string)$row['validation_status']):'Pending') ?><?php if(!empty($row['validation_reason'])):?><div class="muted" style="font-size:12px;max-width:280px"><?= e((string)$row['validation_reason']) ?></div><?php endif;?></td>
          <td><?= (int)$row['attempts'] ?></td>
          <td><?= e((string)$row['created_at']) ?></td>
          <td>
            <?php if(in_array((string)$row['status'],['blocked','failed'],true)): ?>
              <form method="post" action="<?= e(url('admin/sender/action')) ?>" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="action" value="retry"><input type="hidden" name="tab" value="queue"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="button secondary">Retry</button></form>
            <?php endif; ?>
            <?php if((string)$row['status']!=='sent'): ?>
              <form method="post" action="<?= e(url('admin/sender/action')) ?>" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="tab" value="queue"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="button secondary" onclick="return confirm('Delete this unsent queue item?')">Delete</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<?php elseif($tab==='history'): ?>
<section class="admin-card">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:14px">
    <div><h2 style="margin:0 0 4px">Email history</h2><p class="muted" style="margin:0">Local audit trail for queued, blocked, failed and successfully sent template messages.</p></div>
    <form method="get" action="<?= e(url('admin/sender')) ?>" style="display:flex;gap:8px;align-items:end">
      <input type="hidden" name="tab" value="history">
      <label>Status<select name="status"><?php foreach(['all','sent','blocked','failed','queued','processing'] as $s): ?><option value="<?= e($s) ?>" <?= $queueStatus===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></label>
      <button class="button secondary">Filter</button>
    </form>
  </div>
  <?php if(empty($queueRows)): ?><div class="empty-state">No email history found.</div><?php else: ?>
  <div class="table-wrap"><table class="admin-table">
    <thead><tr><th>Recipient</th><th>Template</th><th>Status</th><th>Provider ID</th><th>Sent</th><th>Cleaner</th></tr></thead>
    <tbody><?php foreach($queueRows as $row): ?><tr>
      <td><?= e((string)$row['recipient_email']) ?></td>
      <td><?= e((string)($row['template_title']?:$row['template_id'])) ?></td>
      <td><strong><?= e(ucfirst((string)$row['status'])) ?></strong><?php if(!empty($row['last_error'])):?><div class="muted" style="font-size:12px;max-width:360px"><?= e((string)$row['last_error']) ?></div><?php endif;?></td>
      <td><?= e((string)($row['provider_message_id']??'')) ?></td>
      <td><?= e((string)($row['sent_at']??'')) ?></td>
      <td><?= e((string)($row['validation_status']??'pending')) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php elseif($tab==='settings'): ?>
<section class="admin-card" style="margin-bottom:18px">
  <h2 style="margin-top:0">Sender configuration</h2>
  <div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:20px">
    <div class="stat-card"><span>API token</span><strong><?= $providerConfigured?'Configured':'Missing' ?></strong></div>
    <div class="stat-card"><span>Connection</span><strong><?= $providerConfigured && !$providerError?'Available':($providerConfigured?'Error':'Waiting for token') ?></strong></div>
    <div class="stat-card"><span>Templates visible</span><strong><?= number_format(count($templates)) ?></strong></div>
    <div class="stat-card"><span>Daily send limit</span><strong><?= number_format($dailyLimit) ?></strong></div>
  </div>

  <form method="post" action="<?= e(url('admin/sender/action')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_settings">
    <input type="hidden" name="tab" value="settings">

    <div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
      <label style="grid-column:1/-1">Sender API token
        <input type="password" name="api_token" autocomplete="new-password" placeholder="<?= $providerConfigured?'•••••••••••••••• — leave blank to keep current token':'Paste Sender API token' ?>">
        <small><?= $providerConfigured?'A token is already stored securely. Enter a new token only to replace it.':'The token will be encrypted before it is stored.' ?></small>
      </label>

      <label>Daily successful-send limit
        <input type="number" name="daily_limit" min="1" max="1000" value="<?= (int)($senderSettings['daily_limit']??50) ?>" required>
        <small>Maximum successful Sender deliveries per Asia/Kolkata calendar day.</small>
      </label>

      <label>Worker batch size
        <input type="number" name="batch_size" min="1" max="100" value="<?= (int)($senderSettings['batch_size']??50) ?>" required>
        <small>Maximum queue rows examined each time the cron worker runs.</small>
      </label>
    </div>

    <?php if($providerConfigured): ?>
      <label style="display:flex;gap:8px;align-items:center;margin:14px 0">
        <input type="checkbox" name="remove_api_token" value="1" style="width:auto">
        <span>Remove the stored Sender API token</span>
      </label>
    <?php endif; ?>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
      <button class="button" type="submit">Save Sender settings</button>
    </div>
  </form>
</section>

<section class="admin-card">
  <h2 style="margin-top:0">Connection &amp; worker</h2>
  <table class="admin-table" style="margin-bottom:20px">
    <tbody>
      <tr><th style="width:260px">API token storage</th><td><?= $providerConfigured?'Encrypted and hidden':'Not configured' ?></td></tr>
      <tr><th>Daily successful-send cap</th><td><?= number_format($dailyLimit) ?></td></tr>
      <tr><th>Worker batch size</th><td><?= number_format($batchSize) ?></td></tr>
      <tr><th>List cleaner</th><td><code><?= e((string)env('EMAIL_VALIDATOR_API_URL','https://mediapitch.in/mail-list-cleaner/api.php')) ?></code></td></tr>
      <tr><th>Timezone used for daily cap</th><td><code><?= e((string)env('CONTENT_TIMEZONE','Asia/Kolkata')) ?></code></td></tr>
      <tr><th>Cron command</th><td><code>php database/sender-worker.php</code></td></tr>
    </tbody>
  </table>

  <form method="post" action="<?= e(url('admin/sender/action')) ?>">
    <?= Csrf::field() ?><input type="hidden" name="action" value="test_connection"><input type="hidden" name="tab" value="settings">
    <button class="button" <?= !$providerConfigured?'disabled':'' ?>>Test Sender connection</button>
  </form>

  <p class="muted" style="margin-top:18px">
    The API token is encrypted with the application's existing secret-storage mechanism and is never shown back in the dashboard.
    Environment variables remain supported only as a fallback for existing deployments.
  </p>
</section>
<?php endif; ?>
