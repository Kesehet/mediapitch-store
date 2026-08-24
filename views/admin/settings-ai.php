<?php use MediaPitch\Core\Csrf; ?>
<section class="panel form-panel settings-panel">
  <div class="panel-head"><div><h2>Autonomous AI content</h2><p>Research and prepare editorial drafts with Ollama. AI-created content is always saved as draft for human review.</p></div></div>
  <form method="post" action="<?= e(url('admin/settings/ai/save')) ?>">
    <?= Csrf::field() ?>
    <div class="settings-subpanel">
      <div class="settings-section-head compact"><h3>Master switch</h3><p>When disabled, the worker exits without researching, generating or emailing.</p></div>
      <label class="check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled'])?'checked':'' ?>> Enable autonomous AI content generation</label>
    </div>

    <div class="settings-subpanel">
      <div class="settings-section-head compact"><h3>Ollama</h3><p>The CMS talks to Ollama over HTTP. The Ollama host can be on another machine.</p></div>
      <div class="form-grid">
        <label class="span-2">Ollama URL<input name="ollama_url" required maxlength="500" value="<?= e($settings['ollama_url']??'http://127.0.0.1:11434') ?>" placeholder="http://127.0.0.1:11434"></label>
        <label>Model<input name="model" required maxlength="150" value="<?= e($settings['model']??'qwen3:30b') ?>" placeholder="qwen3:30b"></label>
        <label>AI draft owner<select name="author_id"><option value="0">First active admin/editor</option><?php foreach(($users??[]) as $user): ?><option value="<?= (int)$user['id'] ?>" <?= (int)($settings['author_id']??0)===(int)$user['id']?'selected':'' ?>><?= e($user['name'].' — '.$user['email']) ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="form-actions"><button class="secondary-button" type="submit" formaction="<?= e(url('admin/settings/ai/test')) ?>">Test Ollama connection</button></div>
    </div>

    <div class="settings-subpanel">
      <div class="settings-section-head compact"><h3>Autonomy &amp; research</h3><p>Control how often the agent works and how much research it performs before writing.</p></div>
      <div class="form-grid">
        <label>Maximum drafts per day<input type="number" name="max_drafts_per_day" min="1" max="20" value="<?= (int)($settings['max_drafts_per_day']??2) ?>"></label>
        <label>Research depth<select name="research_depth"><option value="quick" <?= ($settings['research_depth']??'')==='quick'?'selected':'' ?>>Quick — 3 searches</option><option value="standard" <?= ($settings['research_depth']??'')==='standard'?'selected':'' ?>>Standard — 5 searches</option><option value="thorough" <?= ($settings['research_depth']??'thorough')==='thorough'?'selected':'' ?>>Thorough — 8 searches</option></select></label>
      </div>
      <div class="settings-toggle-list">
        <label class="check"><input type="checkbox" name="auto_discover" value="1" <?= !empty($settings['auto_discover'])?'checked':'' ?>> Let AI discover content opportunities automatically</label>
        <label class="check"><input type="checkbox" name="allow_blog" value="1" <?= !empty($settings['allow_blog'])?'checked':'' ?>> Allow blog/article drafts</label>
        <label class="check"><input type="checkbox" name="allow_guides" value="1" <?= !empty($settings['allow_guides'])?'checked':'' ?>> Allow buying-guide drafts</label>
      </div>
      <p class="muted"><strong>Publishing safeguard:</strong> the worker has no publish action. It writes <code>status=draft</code> and <code>robots_index=0</code>. A CMS user must review and publish manually.</p>
    </div>

    <div class="settings-subpanel">
      <div class="settings-section-head compact"><h3>Email notifications</h3><p>Send the completed draft, summary, research count and CMS review link to one or more personal email addresses.</p></div>
      <div class="form-grid">
        <label class="span-2">Notification emails <small>One per line, or comma-separated.</small><textarea name="notification_emails" rows="4" placeholder="editor@example.com"><?= e($settings['notification_emails']??'') ?></textarea></label>
        <label class="span-2">From email <small>Optional. Your server must be configured to send PHP mail.</small><input type="email" name="notification_from" maxlength="255" value="<?= e($settings['notification_from']??'') ?>" placeholder="cms@mediapitch.in"></label>
      </div>
    </div>
    <div class="form-actions settings-save-bar"><span class="muted">Disabling the master switch stops new worker activity.</span><button class="primary-button">Save AI settings</button></div>
  </form>
</section>

<section class="panel form-panel">
  <div class="panel-head"><div><h2>Queue a draft manually</h2><p>Useful for testing the complete research → draft → email workflow.</p></div></div>
  <form method="post" action="<?= e(url('admin/settings/ai/queue')) ?>">
    <?= Csrf::field() ?>
    <div class="form-grid">
      <label class="span-2">Topic<input name="topic" required maxlength="500" placeholder="Best laptops for students under ₹50,000"></label>
      <label>Content type<select name="content_type"><option value="blog">Blog article</option><option value="buying_guide">Buying guide</option></select></label>
    </div>
    <div class="form-actions"><button class="primary-button">Queue AI draft</button></div>
  </form>
</section>

<section class="panel">
  <div class="panel-head"><div><h2>Recent AI activity</h2><p>Research and writing jobs remain visible for review and debugging.</p></div></div>
  <div class="table-wrap"><table><thead><tr><th>ID</th><th>Topic</th><th>Type</th><th>Status</th><th>Stage</th><th>Draft</th><th>Created</th></tr></thead><tbody>
  <?php if(empty($jobs)): ?><tr><td colspan="7" class="muted">No AI jobs yet.</td></tr><?php endif; ?>
  <?php foreach(($jobs??[]) as $job): ?><tr>
    <td>#<?= (int)$job['id'] ?></td><td><?= e($job['topic']) ?></td><td><?= e($job['content_type']) ?></td><td><?= e($job['status']) ?></td><td><?= e($job['stage']??'') ?></td>
    <td><?php if(!empty($job['content_id'])): $editBase=$job['content_type']==='buying_guide'?'admin/guides/':'admin/blog/'; ?><a href="<?= e(url($editBase.(int)$job['content_id'].'/edit')) ?>">Review draft</a><?php elseif(!empty($job['error_message'])): ?><span title="<?= e($job['error_message']) ?>">Failed</span><?php else: ?>—<?php endif; ?></td>
    <td><?= e($job['created_at']??'') ?></td>
  </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
