document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('textarea[name="body"]').forEach(textarea=>{
    if(textarea.dataset.richEditor==='1')return;
    textarea.dataset.richEditor='1';
    const wrap=document.createElement('div');wrap.className='mp-editor';textarea.parentNode.insertBefore(wrap,textarea);wrap.appendChild(textarea);
    const toolbar=document.createElement('div');toolbar.className='mp-editor-toolbar';
    const apply=(open,close)=>{const start=textarea.selectionStart,end=textarea.selectionEnd,selected=textarea.value.slice(start,end)||'Text';textarea.setRangeText(open+selected+close,start,end,'end');textarea.focus();};
    [['P','p'],['H2','h2'],['H3','h3'],['Bold','strong'],['Italic','em'],['Quote','blockquote'],['Code','code']].forEach(([label,tag])=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.addEventListener('click',()=>apply('<'+tag+'>','</'+tag+'>'));toolbar.appendChild(b);});
    [['Bullets','ul'],['Numbered','ol']].forEach(([label,tag])=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.addEventListener('click',()=>{const start=textarea.selectionStart,end=textarea.selectionEnd;const selected=textarea.value.slice(start,end)||'Item';const items=selected.split(/\r?\n/).filter(Boolean).map(v=>'<li>'+v+'</li>').join('\n');textarea.setRangeText('<'+tag+'>\n'+items+'\n</'+tag+'>',start,end,'end');textarea.focus();});toolbar.appendChild(b);});

    const link=document.createElement('button');link.type='button';link.textContent='Link';link.addEventListener('click',()=>{const href=prompt('Link URL (https://, /path, #anchor or mailto:)');if(!href)return;if(!/^(https?:\/\/|\/|#|mailto:)/i.test(href)){alert('Use an http(s), site-relative, anchor or mailto URL.');return;}apply('<a href="'+href.replace(/"/g,'&quot;')+'">','</a>');});toolbar.appendChild(link);

    const internal=document.createElement('button');internal.type='button';internal.textContent='Internal link';internal.title='Search MediaPitch content and insert an internal link';internal.addEventListener('click',async()=>{
      const query=prompt('Search MediaPitch for a product, guide, category, comparison, review or article');if(!query||query.trim().length<2)return;
      internal.disabled=true;
      try{
        const response=await fetch('/api/search-suggestions?q='+encodeURIComponent(query.trim()),{headers:{'Accept':'application/json'}});
        if(!response.ok)throw new Error('Search failed');
        const data=await response.json();const suggestions=Array.isArray(data.suggestions)?data.suggestions:[];
        if(!suggestions.length){alert('No matching internal pages found.');return;}
        const choices=suggestions.map((item,i)=>(i+1)+'. ['+item.type+'] '+item.label).join('\n');
        const chosen=prompt('Choose a result number:\n\n'+choices,'1');if(!chosen)return;
        const index=parseInt(chosen,10)-1;if(!Number.isInteger(index)||!suggestions[index]){alert('Choose one of the listed result numbers.');return;}
        const item=suggestions[index];const start=textarea.selectionStart,end=textarea.selectionEnd;const selected=textarea.value.slice(start,end)||item.label;
        textarea.setRangeText('<a href="'+String(item.url).replace(/"/g,'&quot;')+'">'+selected+'</a>',start,end,'end');textarea.focus();
      }catch(error){alert('Internal search is temporarily unavailable. You can still use the Link button with a /path URL.');}
      finally{internal.disabled=false;}
    });toolbar.appendChild(internal);

    const product=document.createElement('button');product.type='button';product.textContent='Product';product.title='Insert a product by CMS ID';product.addEventListener('click',()=>{const id=prompt('Product ID to embed');if(!id)return;if(!/^\d+$/.test(id.trim())){alert('Enter a numeric product ID.');return;}const start=textarea.selectionStart,end=textarea.selectionEnd;textarea.setRangeText('[product:'+id.trim()+']',start,end,'end');textarea.focus();});toolbar.appendChild(product);
    wrap.insertBefore(toolbar,textarea);
    const help=document.createElement('small');help.className='mp-editor-help';help.textContent='Formatting is sanitized before public output. Internal link searches existing MediaPitch pages; Product inserts a safe [product:ID] card.';wrap.appendChild(help);
  });
  if(document.getElementById('guide-form')){const script=document.createElement('script');script.src='/assets/guide-structure.js';document.head.appendChild(script);}
  if(document.querySelector('input[name="seo_title"],textarea[name="meta_description"]')){const script=document.createElement('script');script.src='/assets/admin-seo-preview.js';document.head.appendChild(script);}
});
