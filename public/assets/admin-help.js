(() => {
  const root = document.querySelector('[data-help-root]');
  if (!root) return;

  // Google Ads configuration is administrator-only, so inject this handbook
  // topic only when the administrator Website Settings topic is present.
  const settingsSection = root.querySelector('#help-settings');
  if (settingsSection && !root.querySelector('#help-google-ads')) {
    const section = document.createElement('section');
    section.id = 'help-google-ads';
    section.className = 'panel help-topic';
    section.dataset.helpSection = '';
    section.dataset.helpKeywords = 'google ads google tag conversion tracking events affiliate amazon click product view site search primary secondary label AW gtag tag assistant';
    section.innerHTML = `
      <div class="help-section-title">
        <span class="help-number">13A</span>
        <div>
          <h2>Google Ads tracking &amp; conversions</h2>
          <p>Connect the site-wide Google tag to meaningful Google Ads conversion actions.</p>
        </div>
        <a class="secondary-button" href="/admin/settings/site">Open Website Settings</a>
      </div>

      <div class="help-callout">
        <strong>Current base tag:</strong> <code>AW-16657488326</code>. The base tag identifies the Google Ads account, but each conversion action also gets its own <strong>conversion label</strong> from Google Ads.
      </div>

      <h3>Events already tracked across the website</h3>
      <div class="help-definition-grid">
        <div><strong>affiliate_click</strong><span>Amazon / affiliate destination clicks. Recommended Google Ads <b>Primary</b> conversion.</span></div>
        <div><strong>view_item</strong><span>Product-detail views. Recommended <b>Secondary</b> conversion if you want it visible in Google Ads.</span></div>
        <div><strong>search</strong><span>Site searches. Recommended <b>Secondary</b> conversion.</span></div>
        <div><strong>view_content</strong><span>Blog, guide, review and comparison views. Analytics signal only by default.</span></div>
        <div><strong>select_item</strong><span>Internal product clicks. Analytics signal only by default.</span></div>
        <div><strong>content_click / outbound_click</strong><span>Editorial navigation and other external links. Analytics signal only by default.</span></div>
      </div>

      <h3>Set up a conversion action in Google Ads</h3>
      <ol class="help-steps">
        <li>Sign in to <strong>Google Ads</strong> and open <strong>Goals → Conversions → Summary</strong>.</li>
        <li>Click <strong>+ Create conversion action</strong>.</li>
        <li>Choose <strong>Conversions on a website</strong> and use <code>store.mediapitch.in</code> as the website/data source.</li>
        <li>Google should detect the existing Google tag <code>AW-16657488326</code>. You do not need to paste the base tag into individual pages.</li>
        <li>Create the conversion using the <strong>Google tag</strong> and choose <strong>Manually using code</strong>. This is the appropriate option for click/action-based conversions rather than a simple thank-you-page URL.</li>
        <li>Name the conversion clearly, for example <strong>MediaPitch – Amazon / Affiliate Click</strong>.</li>
        <li>For the Amazon / Affiliate Click conversion, set <strong>Action optimization = Primary</strong>. This is the strongest commercial-intent action currently measurable on the Store.</li>
        <li>For Product View and Site Search conversions, set <strong>Action optimization = Secondary</strong>. These are useful signals but should not normally drive Smart Bidding.</li>
        <li>For affiliate clicks, do not use the product retail price as conversion value because a click does not prove a purchase. Start with no value or a simple fixed value if your campaign strategy requires one.</li>
        <li>Choose the conversion count method that matches your reporting goal. <strong>Every</strong> counts all affiliate clicks; <strong>One</strong> counts one conversion per ad interaction.</li>
        <li>Save the conversion action and open <strong>See event snippet</strong>.</li>
      </ol>

      <h3>Copy the conversion label into MediaPitch CMS</h3>
      <p>Google will show a destination similar to:</p>
      <div class="help-callout"><code>AW-16657488326/AbCdEfGh123</code></div>
      <ol class="help-steps">
        <li>Copy <strong>only the text after the slash</strong>. In the example above, copy <code>AbCdEfGh123</code>.</li>
        <li>Open <strong>CMS → Website Settings → Google Ads conversions</strong>.</li>
        <li>Paste the label into the matching field:
          <ul class="help-bullets">
            <li><strong>Affiliate / Amazon click label</strong> → the Primary affiliate-click conversion label.</li>
            <li><strong>Product view label</strong> → the Secondary product-view conversion label.</li>
            <li><strong>Site search label</strong> → the Secondary site-search conversion label.</li>
          </ul>
        </li>
        <li>Click <strong>Save website settings</strong>. No website-code change is required when labels change later.</li>
      </ol>

      <div class="help-callout warning">
        <strong>Important:</strong> do not paste the complete event snippet into the CMS label fields. Paste only the conversion label after <code>AW-16657488326/</code>. Also avoid making Product View or Site Search Primary unless the advertising strategy specifically requires bidding toward those softer actions.
      </div>

      <h3>Recommended Google Ads setup</h3>
      <div class="help-role-grid">
        <div><strong>Amazon / Affiliate Click</strong><span>Primary · use for bidding/optimization.</span></div>
        <div><strong>Product View</strong><span>Secondary · observation/funnel signal.</span></div>
        <div><strong>Site Search</strong><span>Secondary · observation/funnel signal.</span></div>
        <div><strong>Other site events</strong><span>Analytics/debugging signals; do not make them Ads conversions by default.</span></div>
      </div>

      <h3>Verify that Google Ads is receiving the events</h3>
      <ol class="help-steps">
        <li>After saving the conversion labels in CMS, open the public Store in a new tab.</li>
        <li>Trigger the action you are testing — for example open a product and click its Amazon/affiliate CTA.</li>
        <li>Use <strong>Google Tag Assistant</strong> to confirm that the Google tag loads and the conversion/event fires.</li>
        <li>In Google Ads, return to <strong>Goals → Conversions → Summary</strong> and check the conversion action's status.</li>
        <li>A new action can initially show <strong>Unverified</strong> or <strong>Inactive</strong>. Allow time for Google to process a real/test event before treating that as an error.</li>
      </ol>

      <details>
        <summary>What if Google gives me a new conversion label later?</summary>
        <p>Do not change the website code. Replace the relevant label under <strong>Website Settings → Google Ads conversions</strong> and save.</p>
      </details>
      <details>
        <summary>Should every tracked event be a Primary conversion?</summary>
        <p>No. Primary conversions can influence Google Ads bidding. Keep strong commercial actions Primary and use softer engagement actions such as product views and searches as Secondary.</p>
      </details>
    `;
    settingsSection.insertAdjacentElement('afterend', section);
  }

  const input = root.querySelector('[data-help-search]');
  const count = root.querySelector('[data-help-count]');
  const sections = Array.from(root.querySelectorAll('[data-help-section]'));

  const normalize = value => String(value || '').toLowerCase().trim();

  const filter = () => {
    const query = normalize(input?.value);
    let visible = 0;

    sections.forEach(section => {
      const haystack = normalize(`${section.dataset.helpKeywords || ''} ${section.textContent || ''}`);
      const match = !query || query.split(/\s+/).every(term => haystack.includes(term));
      section.hidden = !match;
      if (match) visible += 1;
    });

    if (count) {
      count.textContent = query
        ? `${visible} help topic${visible === 1 ? '' : 's'} found`
        : 'Showing all help topics';
    }
  };

  input?.addEventListener('input', filter);

  root.querySelectorAll('a[href^="#help-"]').forEach(link => {
    link.addEventListener('click', () => {
      if (input) input.value = '';
      filter();
    });
  });
})();
