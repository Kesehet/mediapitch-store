document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('form').forEach(form=>{
    const title=form.querySelector('input[name="seo_title"]');
    const meta=form.querySelector('textarea[name="meta_description"]');
    const fallback=form.querySelector('input[name="title"]');
    if(!title&&!meta)return;
    const box=document.createElement('section');box.className='panel';box.style.marginTop='1rem';
    box.innerHTML='<div class="panel-head"><div><h2>Search preview</h2><p class="muted">Approximate Google-style preview using your current SEO fields.</p></div></div><div style="max-width:680px"><div class="seo-preview-title" style="font-size:1.2rem;margin-bottom:.3rem"></div><div class="seo-preview-url muted" style="margin-bottom:.35rem"></div><div class="seo-preview-meta"></div></div>';
    const previewTitle=box.querySelector('.seo-preview-title'),previewUrl=box.querySelector('.seo-preview-url'),previewMeta=box.querySelector('.seo-preview-meta');
    const slug=form.querySelector('input[name="slug"]');
    const update=()=>{const t=(title?.value.trim()||fallback?.value.trim()||'Untitled');previewTitle.textContent=t.length>60?t.slice(0,57)+'…':t;const path=slug?.value.trim()?'/…/'+slug.value.trim():'/…';previewUrl.textContent=location.origin+path;const m=meta?.value.trim()||'Add a meta description to control this snippet.';previewMeta.textContent=m.length>160?m.slice(0,157)+'…':m;};
    [title,meta,fallback,slug].filter(Boolean).forEach(el=>el.addEventListener('input',update));update();
    form.appendChild(box);
  });
});
