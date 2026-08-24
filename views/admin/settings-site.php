<?php use MediaPitch\Core\Csrf; ?>
<section class="panel form-panel settings-panel" data-settings-tabs>
  <div class="panel-head"><div><h2>Website settings</h2><p>Manage branding, tracking, affiliate disclosures and homepage sections.</p></div></div>

  <div class="settings-tabs" role="tablist" aria-label="Website settings sections">
    <button type="button" class="settings-tab is-active" role="tab" aria-selected="true" aria-controls="settings-general" id="settings-tab-general" data-settings-tab="general">General</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" aria-controls="settings-tracking" id="settings-tab-tracking" data-settings-tab="tracking">Tracking &amp; Google Ads</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" aria-controls="settings-affiliate" id="settings-tab-affiliate" data-settings-tab="affiliate">Affiliate</button>
    <button type="button" class="settings-tab" role="tab" aria-selected="false" aria-controls="settings-homepage" id="settings-tab-homepage" data-settings-tab="homepage">Homepage</button>
  </div>

  <form method="post" action="<?= e(url('admin/settings/site/save')) ?>">
    <?= Csrf::field() ?>

    <section class="settings-tab-panel is-active" id="settings-general" role="tabpanel" aria-labelledby="settings-tab-general" data-settings-panel="general">
      <div class="settings-section-head"><h3>General</h3><p>Public identity used across the storefront.</p></div>
      <div class="form-grid">
        <label class="span-2">Site name<input name="name" maxlength="150" required value="<?= e($settings['name']??'MediaPitch Store') ?>"></label>
        <label class="span-2">Tagline<textarea name="tagline" rows="3" maxlength="500"><?= e($settings['tagline']??'') ?></textarea></label>
      </div>
    </section>

    <section class="settings-tab-panel" id="settings-tracking" role="tabpanel" aria-labelledby="settings-tab-tracking" data-settings-panel="tracking" hidden>
      <div class="settings-section-head"><h3>Tracking &amp; Google Ads</h3><p>Configure the site-wide Google tag and conversion mappings.</p></div>
      <div class="form-grid">
        <label class="span-2">Google tag ID <small>Google Ads / Analytics tag, for example AW-16657488326, G-XXXXXXXXXX or GT-XXXXXXXXXX. Leave blank to disable Google tag loading.</small><input name="google_tag_id" maxlength="40" placeholder="AW-16657488326" value="<?= e($settings['google_tag_id']??'') ?>" autocomplete="off"></label>
      </div>

      <div class="settings-subpanel">
        <div class="settings-section-head compact"><h3>Google Ads conversions</h3><p>Paste only the conversion label shown after the slash in Google Ads, for example <code>AW-16657488326/AbCdEf123</code> → <code>AbCdEf123</code>.</p></div>
        <div class="form-grid">
          <label class="span-2">Affiliate / Amazon click label <small>Recommended Primary conversion. Fires when a visitor clicks a /go/ affiliate link or an Amazon link.</small><input name="google_ads_affiliate_label" maxlength="100" placeholder="Conversion label" value="<?= e($settings['google_ads_affiliate_label']??'') ?>" autocomplete="off"></label>
          <label class="span-2">Product view label <small>Recommended Secondary conversion. Fires when a product detail page is viewed.</small><input name="google_ads_product_view_label" maxlength="100" placeholder="Conversion label" value="<?= e($settings['google_ads_product_view_label']??'') ?>" autocomplete="off"></label>
          <label class="span-2">Site search label <small>Recommended Secondary conversion. Fires when a visitor submits the site search form.</small><input name="google_ads_search_label" maxlength="100" placeholder="Conversion label" value="<?= e($settings['google_ads_search_label']??'') ?>" autocomplete="off"></label>
        </div>
        <p class="muted">Leave a label blank to keep tracking the event without counting it as a Google Ads conversion. Other site-wide events remain analytics signals unless explicitly mapped.</p>
        <a class="secondary-button" href="<?= e(url('admin/help#help-google-ads')) ?>">Open Google Ads setup guide ↗</a>
      </div>
    </section>

    <section class="settings-tab-panel" id="settings-affiliate" role="tabpanel" aria-labelledby="settings-tab-affiliate" data-settings-panel="affiliate" hidden>
      <div class="settings-section-head"><h3>Affiliate</h3><p>Control the disclosure shown publicly with affiliate-linked content.</p></div>
      <div class="form-grid">
        <label class="span-2">Affiliate disclosure<textarea name="affiliate_disclosure" rows="6" maxlength="1500"><?= e($settings['affiliate_disclosure']??'') ?></textarea></label>
      </div>
    </section>

    <section class="settings-tab-panel" id="settings-homepage" role="tabpanel" aria-labelledby="settings-tab-homepage" data-settings-panel="homepage" hidden>
      <div class="settings-section-head"><h3>Homepage sections</h3><p>Choose which content groups appear on the public homepage.</p></div>
      <div class="settings-toggle-list">
        <label class="check"><input type="checkbox" name="home_categories" value="1" <?= !empty($settings['home_categories'])?'checked':'' ?>> Popular categories</label>
        <label class="check"><input type="checkbox" name="home_guides" value="1" <?= !empty($settings['home_guides'])?'checked':'' ?>> Featured buying guides</label>
        <label class="check"><input type="checkbox" name="home_comparisons" value="1" <?= !empty($settings['home_comparisons'])?'checked':'' ?>> Latest comparisons</label>
        <label class="check"><input type="checkbox" name="home_products" value="1" <?= !empty($settings['home_products'])?'checked':'' ?>> Top products</label>
        <label class="check"><input type="checkbox" name="home_articles" value="1" <?= !empty($settings['home_articles'])?'checked':'' ?>> Latest articles</label>
      </div>
    </section>

    <div class="form-actions settings-save-bar"><span class="muted">Changes across all tabs are saved together.</span><button class="primary-button">Save website settings</button></div>
  </form>
</section>
<script>
(() => {
  const root=document.querySelector('[data-settings-tabs]');
  if(!root)return;
  const tabs=[...root.querySelectorAll('[data-settings-tab]')];
  const panels=[...root.querySelectorAll('[data-settings-panel]')];
  const storageKey='mediapitch.websiteSettingsTab';

  const activate=name=>{
    if(!tabs.some(tab=>tab.dataset.settingsTab===name))name='general';
    tabs.forEach(tab=>{
      const active=tab.dataset.settingsTab===name;
      tab.classList.toggle('is-active',active);
      tab.setAttribute('aria-selected',active?'true':'false');
      tab.tabIndex=active?0:-1;
    });
    panels.forEach(panel=>{
      const active=panel.dataset.settingsPanel===name;
      panel.classList.toggle('is-active',active);
      panel.hidden=!active;
    });
    try{sessionStorage.setItem(storageKey,name);}catch(e){}
  };

  let initial='general';
  if(location.hash==='#tracking'||location.hash==='#google-ads')initial='tracking';
  else{try{initial=sessionStorage.getItem(storageKey)||'general';}catch(e){}}
  activate(initial);

  tabs.forEach((tab,index)=>{
    tab.addEventListener('click',()=>activate(tab.dataset.settingsTab));
    tab.addEventListener('keydown',event=>{
      if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
      event.preventDefault();
      let next=index;
      if(event.key==='ArrowRight')next=(index+1)%tabs.length;
      if(event.key==='ArrowLeft')next=(index-1+tabs.length)%tabs.length;
      if(event.key==='Home')next=0;
      if(event.key==='End')next=tabs.length-1;
      activate(tabs[next].dataset.settingsTab);
      tabs[next].focus();
    });
  });
})();
</script>
