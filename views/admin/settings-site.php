<?php use MediaPitch\Core\Csrf; ?>
<section class="panel form-panel">
  <div class="panel-head"><div><h2>Website settings</h2><p>Control public branding, tracking, disclosure text and homepage sections.</p></div></div>
  <form method="post" action="<?= e(url('admin/settings/site/save')) ?>">
    <?= Csrf::field() ?>
    <div class="form-grid">
      <label class="span-2">Site name<input name="name" maxlength="150" required value="<?= e($settings['name']??'MediaPitch Store') ?>"></label>
      <label class="span-2">Tagline<textarea name="tagline" rows="3" maxlength="500"><?= e($settings['tagline']??'') ?></textarea></label>
      <label class="span-2">Google tag ID <small>Google Ads / Analytics tag, for example AW-16657488326, G-XXXXXXXXXX or GT-XXXXXXXXXX. Leave blank to disable Google tag loading.</small><input name="google_tag_id" maxlength="40" placeholder="AW-16657488326" value="<?= e($settings['google_tag_id']??'') ?>" autocomplete="off"></label>
      <label class="span-2">Affiliate disclosure<textarea name="affiliate_disclosure" rows="4" maxlength="1500"><?= e($settings['affiliate_disclosure']??'') ?></textarea></label>
    </div>
    <h3 style="margin-top:1.5rem">Homepage sections</h3>
    <label class="check"><input type="checkbox" name="home_categories" value="1" <?= !empty($settings['home_categories'])?'checked':'' ?>> Popular categories</label>
    <label class="check"><input type="checkbox" name="home_guides" value="1" <?= !empty($settings['home_guides'])?'checked':'' ?>> Featured buying guides</label>
    <label class="check"><input type="checkbox" name="home_comparisons" value="1" <?= !empty($settings['home_comparisons'])?'checked':'' ?>> Latest comparisons</label>
    <label class="check"><input type="checkbox" name="home_products" value="1" <?= !empty($settings['home_products'])?'checked':'' ?>> Top products</label>
    <label class="check"><input type="checkbox" name="home_articles" value="1" <?= !empty($settings['home_articles'])?'checked':'' ?>> Latest articles</label>
    <div class="form-actions"><button class="primary-button">Save website settings</button></div>
  </form>
</section>
