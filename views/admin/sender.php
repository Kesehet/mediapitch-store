<?php
use MediaPitch\Core\Csrf;

$tabLabels = [
    'dashboard' => 'Dashboard',
    'campaigns' => 'Campaigns',
    'templates' => 'Templates',
    'queue' => 'Send Queue',
    'history' => 'History',
    'settings' => 'Settings',
];
$previewContent = '';
if (is_array($selectedTemplate)) {
    $previewContent = (string)($selectedTemplate['content'] ?? $selectedTemplate['html'] ?? '');
}
$campaignPreviewContent = '';
if (is_array($selectedCampaign)) {
    $campaignHtml = is_array($selectedCampaign['html'] ?? null) ? $selectedCampaign['html'] : [];
    $campaignPreviewContent = (string)($campaignHtml['html_content'] ?? $selectedCampaign['html_content'] ?? $selectedCampaign['content'] ?? '');
}
$remainingToday = (int)($stats['remaining_today'] ?? 0);
$dailyLimit = (int)($stats['daily_limit'] ?? 50);
$batchSize = (int)($senderSettings['batch_size'] ?? 50);
$apiCooldownUntil = trim((string)($senderApiStatus['cooldown_until'] ?? ''));
$apiCooldownTs = $apiCooldownUntil !== '' ? strtotime($apiCooldownUntil . ' UTC') : false;
$senderApiCooling = $apiCooldownTs !== false && $apiCooldownTs > time();
$apiRemaining = isset($senderApiStatus['rate_limit_remaining']) && $senderApiStatus['rate_limit_remaining'] !== null
    ? (int)$senderApiStatus['rate_limit_remaining']
    : null;
$apiLimit = isset($senderApiStatus['rate_limit_limit']) && $senderApiStatus['rate_limit_limit'] !== null
    ? (int)$senderApiStatus['rate_limit_limit']
    : null;
$apiResetAt = trim((string)($senderApiStatus['rate_limit_reset_at'] ?? ''));
?>
<section class="admin-card" style="margin-bottom:18px">
  <div style="display:flex;gap:16px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap">
    <div>
      <h2 style="margin:0 0 6px">Sender Email Center</h2>
      <p class="muted" style="margin:0;max-width:780px">
        Control Sender transactional templates and marketing campaigns from MediaPitch. The same validated send queue and daily cap protect both paths.
        Only addresses returning <strong>clean</strong> are dispatched.
      </p>
    </div>
    <div style="text-align:right">
      <div><strong>Sender:</strong> <?= !$providerConfigured ? 'Not configured' : ($senderApiCooling ? 'API cooling down' : ($providerError ? 'Connection error' : 'Connected')) ?></div>
      <div class="muted" style="font-size:13px">Daily cap: <?= number_format($dailyLimit) ?> · Remaining today: <?= number_format($remainingToday) ?></div>
      <?php if($apiRemaining!==null): ?>
        <div class="muted" style="font-size:12px">API requests: <?= number_format($apiRemaining) ?><?= $apiLimit!==null?' / '.number_format($apiLimit):'' ?> remaining<?= $apiResetAt!==''?' · reset '.$apiResetAt.' UTC':'' ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<nav class="email-tabs" aria-label="Email Center sections">
  <?php foreach($tabLabels as $tabKey=>$tabLabel): ?>
    <a class="email-tab-link <?= $tab===$tabKey?'is-active':'' ?>" href="<?= e(url('admin/sender').'?tab='.$tabKey) ?>"><?= e($tabLabel) ?></a>
  <?php endforeach; ?>
  <a class="email-tab-link <?= str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/admin/newsletter')?'is-active':'' ?>" href="<?= e(url('admin/newsletter')) ?>">Subscribers</a>
</nav>

<?php if($senderApiCooling): ?>
  <div class="flash error" style="margin-bottom:18px">
    Sender API is temporarily rate-limited until <?= e($apiCooldownUntil) ?> UTC.
    Existing queues and cached Sender data are preserved; workers will resume after the cooldown.
  </div>
<?php elseif($providerError): ?>
  <div class="flash error" style="margin-bottom:18px"><?= e($providerError) ?></div>
<?php endif; ?>

<?php if($tab==='dashboard'): ?>
<section class="admin-card">
  <div class="admin-grid stats-grid" style="margin-bottom:22px">
    <div class="stat-card"><span>Sent today</span><strong><?= number_format((int)($stats['sent_today']??0)) ?></strong><small><?= number_format((int)($stats['transactional_sent_today']??0)) ?> template · <?= number_format((int)($stats['campaign_sent_today']??0)) ?> campaign</small></div>
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
        <button class="button" type="submit" <?= !$providerConfigured||$remainingToday<1||$senderApiCooling?'disabled':'' ?>>Process queue now</button>
      </form>
    </div>
  </div>
</section>

<?php elseif($tab==='campaigns'): ?>
<section class="admin-card" style="margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px">
    <div>
      <h2 style="margin:0 0 4px">Sender email campaigns</h2>
      <p class="muted" style="margin:0;max-width:760px">Choose an existing Sender campaign as the master design. MediaPitch snapshots its subject, sender details and HTML, then dispatches the local clean subscriber audience in daily batches without sending the master campaign itself.</p>
    </div>
    <div style="text-align:right">
      <strong><?= number_format(count($campaigns)) ?> campaign(s)</strong>
      <div class="muted" style="font-size:13px">Shared allowance remaining today: <?= number_format((int)($campaignStats['remaining_today']??0)) ?></div>
    </div>
  </div>

  <?php if(!$providerConfigured): ?>
    <div class="empty-state">Open <strong>Settings</strong>, add your Sender API token, save it, then return here.</div>
  <?php elseif(empty($campaigns)): ?>
    <div class="empty-state">No Sender email campaigns were returned. Create the email design in Sender first, then refresh this page.</div>
  <?php else: ?>
    <div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px">
      <?php foreach($campaigns as $campaign): $campaignId=(string)($campaign['id']??''); ?>
        <article style="border:1px solid #e5e7eb;border-radius:12px;padding:16px">
          <div class="muted" style="font-size:12px;margin-bottom:5px"><?= e((string)($campaign['status']??'Campaign')) ?> · <?= e($campaignId) ?></div>
          <h3 style="margin:0 0 6px"><?= e((string)($campaign['title']??$campaign['subject']??'Untitled campaign')) ?></h3>
          <p style="margin:0 0 8px"><?= e((string)($campaign['subject']??'')) ?></p>
          <div class="muted" style="font-size:12px;margin-bottom:12px">Sender audience: <?= number_format((int)($campaign['recipient_count']??0)) ?> · This number is not used by the MediaPitch queue.</div>
          <a class="button" href="<?= e(url('admin/sender').'?'.http_build_query(['tab'=>'campaigns','campaign'=>$campaignId])) ?>">Preview &amp; queue</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if($selectedCampaign): ?>
<section class="admin-card" style="margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap">
    <div>
      <div class="muted" style="font-size:12px"><?= e((string)($selectedCampaign['id']??'')) ?></div>
      <h2 style="margin:4px 0"><?= e((string)($selectedCampaign['title']??$selectedCampaign['subject']??'Campaign')) ?></h2>
      <p style="margin:0"><strong>Subject:</strong> <?= e((string)($selectedCampaign['subject']??'')) ?></p>
      <?php if(!empty($selectedCampaign['_sender_cache_stale'])): ?>
        <div class="muted" style="font-size:12px;margin-top:6px">
          Showing the last cached Sender copy because the live API is unavailable
          <?php if(!empty($selectedCampaign['_sender_cache_synced_at'])): ?> · cached <?= e((string)$selectedCampaign['_sender_cache_synced_at']) ?> UTC<?php endif; ?>.
        </div>
      <?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('admin/sender/action')) ?>" style="min-width:min(100%,360px)">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="queue_campaign">
      <input type="hidden" name="tab" value="campaigns">
      <input type="hidden" name="campaign_id" value="<?= e((string)($selectedCampaign['id']??'')) ?>">
      <label style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px">
        <input type="checkbox" name="consent_confirmed" value="1" required style="width:auto;margin-top:3px">
        <span>Use the full merged active audience from MediaPitch + Sender. Every address will be run through the MediaPitch cleaner now, and only addresses returning <strong>clean</strong> will be queued.</span>
      </label>
      <label style="display:flex;gap:8px;align-items:flex-start;margin-bottom:12px">
        <input type="checkbox" name="auto_continue" value="1" checked style="width:auto;margin-top:3px">
        <span>Continue automatically on future worker runs until the audience is finished.</span>
      </label>
      <?php if($mergedAudienceError): ?>
        <div class="flash error" style="margin-bottom:10px">Merged audience unavailable: <?= e((string)$mergedAudienceError) ?></div>
      <?php elseif($mergedAudienceTruncated): ?>
        <div class="flash error" style="margin-bottom:10px">Sender subscriber data is incomplete, so campaign queueing is disabled until a complete snapshot is available.</div>
      <?php else: ?>
        <?php if(!empty($mergedAudienceWarning)): ?>
          <div class="flash" style="margin-bottom:10px"><?= e((string)$mergedAudienceWarning) ?></div>
        <?php endif; ?>
        <div class="muted" style="font-size:12px;margin-bottom:10px">
          Current merged audience: <strong><?= number_format((int)($mergedAudienceStats['unique_total']??0)) ?></strong> unique ·
          <strong><?= number_format((int)($mergedAudienceStats['effective_active']??0)) ?></strong> active candidates before live cleaning.
          <?php if(!empty($mergedAudienceLastSyncedAt)): ?><br>Sender snapshot: <?= e((string)$mergedAudienceLastSyncedAt) ?> UTC<?php endif; ?>
          <?php if(!empty($mergedAudienceRefreshAfter)): ?> · API refresh paused until <?= e((string)$mergedAudienceRefreshAfter) ?> UTC<?php endif; ?>
        </div>
      <?php endif; ?>
      <button class="button" type="submit" <?= $mergedAudienceError||$mergedAudienceTruncated?'disabled':'' ?>>Clean &amp; queue campaign</button>
    </form>
  </div>
  <?php if($campaignPreviewContent!==''): ?>
    <iframe sandbox title="Sender campaign preview" srcdoc="<?= e($campaignPreviewContent) ?>" style="width:100%;height:650px;border:1px solid #e5e7eb;border-radius:10px;margin-top:16px;background:white"></iframe>
  <?php else: ?>
    <div class="empty-state" style="margin-top:16px">Sender did not expose reusable campaign content for this email. MediaPitch will refuse to queue it unless Sender returns the subject, sender, reply-to and content safely.</div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="admin-card">
  <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <h2 style="margin:0 0 4px">Campaign queues</h2>
      <p class="muted" style="margin:0">Each queue is a snapshot of one Sender design plus the merged MediaPitch + Sender audience that passed the live cleaner before queueing. The worker validates addresses again immediately before sending.</p>
    </div>
    <div class="muted" style="font-size:13px">
      Active: <?= number_format((int)($campaignStats['active_runs']??0)) ?> ·
      Dispatched: <?= number_format((int)($campaignStats['dispatched']??0)) ?>
    </div>
  </div>

  <?php if(empty($campaignRuns)): ?>
    <div class="empty-state">No campaign queues yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table class="admin-table">
      <thead><tr><th>Campaign</th><th>Progress</th><th>Status</th><th>Last issue</th><th>Controls</th></tr></thead>
      <tbody>
      <?php foreach($campaignRuns as $run): $runStatus=(string)$run['status']; $remaining=max(0,(int)($run['remaining_recipients']??0)); ?>
        <tr>
          <td>
            <strong><?= e((string)($run['source_title']?:$run['subject'])) ?></strong>
            <div class="muted" style="font-size:11px">Source <?= e((string)$run['source_campaign_id']) ?> · <?= (int)($run['batch_count']??0) ?> batch(es)</div>
          </td>
          <td>
            <strong><?= number_format((int)$run['dispatched_recipients']) ?> / <?= number_format((int)$run['total_recipients']) ?></strong>
            <div class="muted" style="font-size:12px"><?= number_format($remaining) ?> remaining · <?= number_format((int)$run['blocked_recipients']) ?> blocked · <?= number_format((int)$run['failed_recipients']) ?> failed</div>
            <?php if($remaining>0): ?>
              <div class="muted" style="font-size:11px;margin-top:3px">
                <?= number_format((int)($run['ready_recipients']??0)) ?> ready ·
                <?= number_format((int)($run['waiting_recipients']??0)) ?> waiting for retry ·
                <?= number_format((int)($run['processing_recipients']??0)) ?> processing
                <?php if(!empty($run['next_retry_at'])): ?> · next retry <?= e((string)$run['next_retry_at']) ?> UTC<?php endif; ?>
              </div>
            <?php endif; ?>
          </td>
          <td><strong><?= e(ucfirst($runStatus)) ?></strong><div class="muted" style="font-size:12px"><?= !empty($run['auto_continue'])?'Auto continue':'Manual' ?></div></td>
          <td><?php if(!empty($run['last_error'])):?><span style="max-width:320px;display:block"><?= e((string)$run['last_error']) ?></span><?php else:?><span class="muted">—</span><?php endif;?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php if(in_array($runStatus,['queued','active'],true)&&$remaining>0): ?>
                <form method="post" action="<?= e(url('admin/sender/action')) ?>">
                  <?= Csrf::field() ?><input type="hidden" name="action" value="process_campaign"><input type="hidden" name="tab" value="campaigns"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>">
                  <input type="hidden" name="limit" value="<?= max(1,min(50,(int)($campaignStats['remaining_today']??50))) ?>">
                  <button class="button" <?= (int)($campaignStats['remaining_today']??0)<1||$senderApiCooling?'disabled':'' ?>>Send today's batch</button>
                </form>
                <form method="post" action="<?= e(url('admin/sender/action')) ?>"><?= Csrf::field() ?><input type="hidden" name="action" value="pause_campaign"><input type="hidden" name="tab" value="campaigns"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>"><button class="button secondary">Pause</button></form>
              <?php elseif($runStatus==='paused'&&$remaining>0): ?>
                <form method="post" action="<?= e(url('admin/sender/action')) ?>"><?= Csrf::field() ?><input type="hidden" name="action" value="resume_campaign"><input type="hidden" name="tab" value="campaigns"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>"><button class="button">Resume</button></form>
              <?php endif; ?>
              <?php if(!in_array($runStatus,['completed','cancelled'],true)): ?>
                <form method="post" action="<?= e(url('admin/sender/action')) ?>" onsubmit="return confirm('Cancel the remaining unsent recipients for this campaign queue?')"><?= Csrf::field() ?><input type="hidden" name="action" value="cancel_campaign"><input type="hidden" name="tab" value="campaigns"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>"><button class="button secondary">Cancel</button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
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
    <div class="empty-state">Open <strong>Settings</strong>, add your Sender API token, save it, then return here.</div>
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
  <p class="muted">Accepted formats: one email per line, <code>email,name</code>, <code>name,email</code>, or <code>Name &lt;email&gt;</code>. Every address must pass the live cleaner as <strong>clean</strong> before it is inserted into the queue, and is validated again immediately before sending.</p>
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
    <button class="button" type="submit" <?= empty($templates)?'disabled':'' ?>>Clean &amp; add to queue</button>
  </form>
</section>

<section class="admin-card" style="margin-bottom:18px">
  <h2 style="margin-top:0">Queue existing newsletter subscribers</h2>
  <p class="muted">This selects currently active local subscribers, runs the live cleaner on every address before queueing, and inserts only addresses returning <strong>clean</strong>. The worker validates them again before the actual send.</p>
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
    <button class="button" type="submit" <?= empty($templates)?'disabled':'' ?>>Clean &amp; queue subscribers</button>
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
      <button class="button" type="submit" <?= !$providerConfigured||$remainingToday<1||$senderApiCooling?'disabled':'' ?>>Process now</button>
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
