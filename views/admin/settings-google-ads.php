<?php
use MediaPitch\Core\Csrf;
$connected=!empty($connection['connected']);
$selected=(string)($connection['customer_id']??'');
?>
<section class="panel form-panel settings-panel">
  <div class="panel-head"><div><h2>Google Ads</h2><p>Connect Google Ads, choose an account, verify MediaPitch conversion actions, and repair only what is missing.</p></div><div class="inline-actions"><a class="secondary-button" href="<?= e(url('admin/settings/site#tracking')) ?>">Website tracking</a></div></div>

  <?php if(!empty($success)): ?><div class="notice success"><?= e($success) ?></div><?php endif; ?>
  <?php if(!empty($error)): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

  <div class="settings-subpanel">
    <div class="settings-section-head compact"><h3>1. Connect Google</h3><p>MediaPitch uses OAuth with the Google Ads scope. The refresh token is encrypted before it is stored.</p></div>
    <?php if(!$configured): ?>
      <div class="notice error">Google Ads API credentials are not configured on the server. Add <code>GOOGLE_ADS_CLIENT_ID</code>, <code>GOOGLE_ADS_CLIENT_SECRET</code> and <code>GOOGLE_ADS_DEVELOPER_TOKEN</code>.</div>
      <p class="muted">Authorized redirect URI: <code><?= e(url('admin/settings/google-ads/callback')) ?></code></p>
    <?php elseif(!$connected): ?>
      <div class="inline-actions"><a class="primary-button" href="<?= e(url('admin/settings/google-ads/connect')) ?>">Connect Google Ads</a></div>
      <p class="muted">Authorized redirect URI: <code><?= e(url('admin/settings/google-ads/callback')) ?></code></p>
    <?php else: ?>
      <p><strong>Connected.</strong> Google authorization is stored securely.</p>
      <form method="post" action="<?= e(url('admin/settings/google-ads/disconnect')) ?>" onsubmit="return confirm('Disconnect Google Ads from MediaPitch? Existing Google Ads conversions and site tracking values will not be deleted.');">
        <?= Csrf::field() ?><button class="secondary-button">Disconnect</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if($connected): ?>
  <div class="settings-subpanel">
    <div class="settings-section-head compact"><h3>2. Choose Google Ads account</h3><p>Google returns accounts directly accessible to the connected user. Manager accounts are supported.</p></div>
    <?php if($accounts): ?>
      <form method="post" action="<?= e(url('admin/settings/google-ads/select')) ?>" class="form-grid">
        <?= Csrf::field() ?>
        <label class="span-2">Google Ads customer
          <select name="customer_id" required>
            <option value="">Choose an account</option>
            <?php foreach($accounts as $account): $id=(string)$account['id']; ?>
              <option value="<?= e($id) ?>" <?= $id===$selected?'selected':'' ?>><?= e((string)$account['name']) ?> — <?= e($id) ?><?= !empty($account['manager'])?' (Manager)':'' ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="span-2"><button class="secondary-button">Use this account</button></div>
      </form>
    <?php else: ?>
      <p class="muted">No directly accessible Google Ads customers were returned for this Google login.</p>
    <?php endif; ?>
    <?php if($selected!==''): ?><p class="muted">Selected: <strong><?= e((string)($connection['account_name']?:$selected)) ?></strong> · Customer ID <?= e($selected) ?></p><?php endif; ?>
  </div>

  <div class="settings-subpanel">
    <div class="settings-section-head compact"><h3>3. Verify &amp; repair conversions</h3><p>This operation is intentionally non-destructive. It checks for the three MediaPitch website conversions and creates only missing ones. Existing unrelated conversions are ignored; conflicting actions are never overwritten.</p></div>
    <div class="table-wrap"><table><thead><tr><th>MediaPitch event</th><th>Google Ads conversion</th><th>Role</th></tr></thead><tbody>
      <tr><td>Affiliate / Amazon click</td><td>MediaPitch - Affiliate click</td><td>Primary · Outbound click</td></tr>
      <tr><td>Product detail view</td><td>MediaPitch - Product view</td><td>Secondary · Page view</td></tr>
      <tr><td>Site search</td><td>MediaPitch - Site search</td><td>Secondary · Engagement</td></tr>
    </tbody></table></div>
    <form method="post" action="<?= e(url('admin/settings/google-ads/verify')) ?>">
      <?= Csrf::field() ?><button class="primary-button" <?= $selected===''?'disabled':'' ?>>Verify &amp; repair Google Ads</button>
    </form>
    <?php if(!empty($connection['last_verified'])): ?><p class="muted">Last successful verification: <?= e((string)$connection['last_verified']) ?> UTC</p><?php endif; ?>
    <?php if(!empty($connection['last_error'])): ?><p class="muted">Last API error: <?= e((string)$connection['last_error']) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if(is_array($report)): ?>
  <div class="settings-subpanel">
    <div class="settings-section-head compact"><h3>Verification result</h3><p>After repair, MediaPitch re-read the account and synchronized the Google tag/conversion labels returned by Google.</p></div>
    <div class="table-wrap"><table><thead><tr><th>Conversion</th><th>Status</th><th>Google ID</th></tr></thead><tbody>
      <?php foreach($report as $item): $action=$item['action']??[]; ?>
        <tr><td><?= e((string)($item['name']??'')) ?></td><td><?= e(ucfirst((string)($item['status']??'unknown'))) ?></td><td><?= e((string)($action['id']??'—')) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <?php endif; ?>

  <div class="notice"><strong>Safety:</strong> this integration does not delete, rename, pause, or modify unrelated Google Ads conversions. If an existing action has the same MediaPitch name but an incompatible type, verification reports a conflict and stops short of changing it.</div>
</section>
