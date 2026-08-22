document.addEventListener('DOMContentLoaded',()=>{
  const escapeHtml=value=>String(value).replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const richTextareas=[...document.querySelectorAll('textarea[name="body"],textarea[name="full_description"]')];

  const cleanWordHtml=html=>{
    const parser=new DOMParser();
    const doc=parser.parseFromString('<div id="mp-word-root">'+html+'</div>','text/html');
    const root=doc.getElementById('mp-word-root');
    if(!root)return html;

    root.querySelectorAll('style,meta,link,xml,script').forEach(node=>node.remove());
    root.querySelectorAll('*').forEach(node=>{
      [...node.attributes].forEach(attr=>{
        const name=attr.name.toLowerCase();
        const value=attr.value;
        if(name==='style'||name==='class'||name.startsWith('xmlns')||name.startsWith('data-')||name.startsWith('o:')||name.startsWith('w:')){
          node.removeAttribute(attr.name);
          return;
        }
        if(name==='id'&&/^_?toc|^_?ref|^mso/i.test(value))node.removeAttribute(attr.name);
      });
    });

    const blockSelector='p,div,li,h1,h2,h3,h4,h5,h6,blockquote,td,th';
    root.querySelectorAll(blockSelector).forEach(block=>{
      let changed=true;
      while(changed){
        changed=false;
        const meaningful=[...block.childNodes].filter(node=>!(node.nodeType===Node.TEXT_NODE&&!node.textContent.trim()));
        if(meaningful.length===1&&meaningful[0].nodeType===Node.ELEMENT_NODE&&/^(STRONG|B)$/.test(meaningful[0].tagName)){
          const wrapper=meaningful[0];
          while(wrapper.firstChild)block.insertBefore(wrapper.firstChild,wrapper);
          wrapper.remove();
          changed=true;
        }
      }
    });

    root.querySelectorAll('span').forEach(span=>{
      if(!span.attributes.length)span.replaceWith(...span.childNodes);
    });

    return root.innerHTML;
  };

  if(richTextareas.length){
    if(typeof window.Jodit==='undefined'){
      console.error('Jodit failed to load; rich text fields remain available as plain textareas.');
    }else{
      richTextareas.forEach(textarea=>{
        if(textarea.dataset.richEditor==='1')return;
        textarea.dataset.richEditor='1';

        const editor=window.Jodit.make(textarea,{
          minHeight:360,
          height:520,
          toolbarAdaptive:false,
          toolbarSticky:true,
          spellcheck:true,
          // Word/Excel paste should keep useful document structure without carrying
          // Microsoft Office font, weight, size and other inline styling into the CMS.
          askBeforePasteFromWord:false,
          processPasteFromWord:true,
          defaultActionOnPasteFromWord:'insert_clear_html',
          defaultActionOnPaste:'insert_as_html',
          buttons:[
            'source','|','undo','redo','|','bold','italic','underline','strikethrough','|',
            'ul','ol','|','paragraph','fontsize','|','align','|','link','table','hr','image','|','eraser','fullsize'
          ],
          buttonsMD:[
            'source','|','undo','redo','|','bold','italic','underline','|','ul','ol','|','paragraph','|','link','table','image','|','fullsize'
          ],
          buttonsSM:['source','|','undo','redo','|','bold','italic','|','ul','ol','|','link','image','|','fullsize'],
          buttonsXS:['source','|','bold','italic','|','ul','ol','|','link','|','fullsize'],
          uploader:{insertImageAsBase64URI:false},
          showCharsCounter:true,
          showWordsCounter:true,
          showXPathInStatusbar:false,
          statusbar:true,
          placeholder:textarea.name==='body'?'Write or paste your content here…':'Write or paste the full product description here…'
        });

        // Word sometimes represents normal paragraphs as <p><strong>whole paragraph</strong></p>.
        // Intercept Office HTML before Jodit handles it, remove Office-only attributes and
        // unwrap only strong/b tags that cover an entire block. Genuine inline bold remains.
        editor.editor.addEventListener('paste',event=>{
          const clipboard=event.clipboardData;
          if(!clipboard)return;
          const html=clipboard.getData('text/html');
          if(!html)return;
          const isOffice=/(class=(['"])?Mso|mso-|urn:schemas-microsoft-com|Microsoft Word|xmlns:o=|xmlns:w=)/i.test(html);
          if(!isOffice)return;
          event.preventDefault();
          event.stopImmediatePropagation();
          editor.s.insertHTML(cleanWordHtml(html));
          editor.synchronizeValues();
        },true);

        const actions=document.createElement('div');
        actions.className='mp-editor-actions';

        const internal=document.createElement('button');
        internal.type='button';
        internal.className='secondary-button';
        internal.textContent='Internal link';
        internal.title='Search MediaPitch content and insert an internal link';
        internal.addEventListener('click',async()=>{
          const query=prompt('Search MediaPitch for a product, guide, category, comparison, review or article');
          if(!query||query.trim().length<2)return;
          internal.disabled=true;
          try{
            const response=await fetch('/api/search-suggestions?q='+encodeURIComponent(query.trim()),{headers:{Accept:'application/json'}});
            if(!response.ok)throw new Error('Search failed');
            const data=await response.json();
            const suggestions=Array.isArray(data.suggestions)?data.suggestions:[];
            if(!suggestions.length){alert('No matching internal pages found.');return;}
            const choices=suggestions.map((item,i)=>(i+1)+'. ['+item.type+'] '+item.label).join('\n');
            const chosen=prompt('Choose a result number:\n\n'+choices,'1');
            if(!chosen)return;
            const index=parseInt(chosen,10)-1;
            if(!Number.isInteger(index)||!suggestions[index]){alert('Choose one of the listed result numbers.');return;}
            const item=suggestions[index];
            editor.s.insertHTML('<a href="'+escapeHtml(item.url)+'">'+escapeHtml(item.label)+'</a>');
          }catch(error){
            alert('Internal search is temporarily unavailable. You can still use the editor Link button with a /path URL.');
          }finally{
            internal.disabled=false;
          }
        });
        actions.appendChild(internal);

        if(textarea.name==='body'){
          const product=document.createElement('button');
          product.type='button';
          product.className='secondary-button';
          product.textContent='Product card';
          product.title='Insert a product by CMS ID';
          product.addEventListener('click',()=>{
            const id=prompt('Product ID to embed');
            if(!id)return;
            if(!/^\d+$/.test(id.trim())){alert('Enter a numeric product ID.');return;}
            editor.s.insertHTML('[product:'+id.trim()+']');
          });
          actions.appendChild(product);
        }

        const help=document.createElement('span');
        help.className='mp-editor-help';
        help.textContent='Paste directly from Microsoft Word or Excel. Office-specific styling and accidental whole-paragraph bold wrappers are cleaned automatically.';
        actions.appendChild(help);
        editor.container.insertAdjacentElement('afterend',actions);
      });
    }
  }

  if(document.getElementById('guide-form')){
    const script=document.createElement('script');
    script.src='/assets/guide-structure.js';
    document.head.appendChild(script);
  }
  if(document.querySelector('input[name="seo_title"],textarea[name="meta_description"]')){
    const script=document.createElement('script');
    script.src='/assets/admin-seo-preview.js';
    document.head.appendChild(script);
  }
});
