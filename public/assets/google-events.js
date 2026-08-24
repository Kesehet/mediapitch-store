(()=>{
  'use strict';

  const cfg=window.MediaPitchTracking||{};
  if(typeof window.gtag!=='function'||!cfg.tagId)return;

  const text=value=>String(value||'').trim().replace(/\s+/g,' ').slice(0,200);
  const path=window.location.pathname||'/';
  const pageTitle=text(document.querySelector('h1')?.textContent||document.title);
  const pageParams={page_path:path,page_title:pageTitle};

  const send=(name,params={})=>{
    try{window.gtag('event',name,{...pageParams,...params});}catch(_e){}
  };

  const sendConversion=(label,params={})=>{
    label=text(label);
    if(!label||!/^[A-Za-z0-9_-]+$/.test(label))return;
    try{
      window.gtag('event','conversion',{
        send_to:cfg.tagId+'/'+label,
        value:1,
        currency:'INR',
        ...params
      });
    }catch(_e){}
  };

  const productMatch=path.match(/^\/product\/([^/?#]+)/);
  if(productMatch){
    const itemId=decodeURIComponent(productMatch[1]);
    send('view_item',{item_id:itemId,item_name:pageTitle});
    sendConversion(cfg.labels?.productView,{item_id:itemId});
  }else{
    const contentMatch=path.match(/^\/(blog|guide|review|compare)\/([^/?#]+)/);
    if(contentMatch){
      send('view_content',{
        content_type:contentMatch[1],
        content_id:decodeURIComponent(contentMatch[2]),
        content_name:pageTitle
      });
    }
  }

  document.addEventListener('submit',event=>{
    const form=event.target;
    if(!(form instanceof HTMLFormElement))return;
    let action;
    try{action=new URL(form.action||window.location.href,window.location.href);}catch(_e){return;}
    if(action.pathname!=='/search')return;
    const field=form.querySelector('input[name="q"]');
    const term=text(field?.value);
    if(!term)return;
    send('search',{search_term:term});
    sendConversion(cfg.labels?.search,{search_term:term});
  },true);

  document.addEventListener('click',event=>{
    const link=event.target.closest?.('a[href]');
    if(!link)return;

    let url;
    try{url=new URL(link.href,window.location.href);}catch(_e){return;}
    const linkText=text(link.textContent||link.getAttribute('aria-label')||'');
    const params={link_url:url.href,link_text:linkText};
    const goMatch=url.origin===window.location.origin?url.pathname.match(/^\/go\/(\d+)/):null;
    const isAmazon=/(^|\.)amazon\.(in|com|co\.uk|ae|sa|de|fr|it|es|ca|com\.au|co\.jp)$/i.test(url.hostname)
      ||/(^|\.)amzn\.(to|eu)$/i.test(url.hostname);

    if(goMatch||isAmazon){
      const productId=goMatch?goMatch[1]:'';
      send('affiliate_click',{...params,product_id:productId,affiliate_network:'amazon'});
      sendConversion(cfg.labels?.affiliateClick,{product_id:productId});
      return;
    }

    if(url.origin!==window.location.origin){
      send('outbound_click',params);
      return;
    }

    const selectedProduct=url.pathname.match(/^\/product\/([^/?#]+)/);
    if(selectedProduct){
      send('select_item',{...params,item_id:decodeURIComponent(selectedProduct[1])});
      return;
    }

    const selectedContent=url.pathname.match(/^\/(blog|guide|review|compare)\/([^/?#]+)/);
    if(selectedContent){
      send('content_click',{
        ...params,
        content_type:selectedContent[1],
        content_id:decodeURIComponent(selectedContent[2])
      });
    }
  },true);
})();
