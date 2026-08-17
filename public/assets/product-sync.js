document.addEventListener('DOMContentLoaded',()=>{
  const state=window.MediaPitchProductSync;
  if(!state||!state.id)return;
  const form=document.querySelector('form[action$="/admin/products/save"]');
  if(!form)return;
  const labels={title:'Product title',main_image_url:'Main image',features_json:'Features',price:'Price & currency',amazon_url:'Amazon URL',affiliate_url:'Affiliate URL'};
  const panel=document.createElement('section');
  panel.className='spec-editor-block amazon-sync-controls';
  const synced=state.last_synced_at||'Never';
  const status=state.asin?`ASIN ${state.asin} · ${state.marketplace||'marketplace not recorded'} · Last synced: ${synced}`:'This product does not currently have an ASIN, so Amazon refresh is unavailable.';
  panel.innerHTML='<div class="panel-head"><div><h2>Amazon sync controls</h2><p class="muted amazon-sync-help"></p></div><span class="badge amazon-sync-source"></span></div><p class="muted amazon-sync-status"></p><div class="form-grid amazon-override-grid"></div><p class="muted"><small>Protected fields keep the value entered in MediaPitch when Amazon refresh runs. Unprotected Amazon-owned fields can be refreshed from Creators API.</small></p>';
  panel.querySelector('.amazon-sync-status').textContent=status;
  panel.querySelector('.amazon-sync-source').textContent=state.source||'manual';
  panel.querySelector('.amazon-sync-help').textContent=state.source==='manual'?'Manual product; controls become useful if you later connect an ASIN.':'Choose which editorial values Amazon refresh must never replace.';
  const grid=panel.querySelector('.amazon-override-grid');
  Object.entries(labels).forEach(([field,label])=>{
    const el=document.createElement('label');el.className='check';
    const input=document.createElement('input');input.type='checkbox';input.name=`amazon_override[${field}]`;input.value='1';input.checked=!!(state.overrides&&state.overrides[field]);
    el.appendChild(input);el.appendChild(document.createTextNode(' Protect '+label));grid.appendChild(el);
  });
  const actions=form.querySelector('.form-actions');
  if(actions)form.insertBefore(panel,actions);else form.appendChild(panel);
});
