(() => {
  const root=document.querySelector('[data-media-library]');
  if(!root) return;

  const toast=root.querySelector('[data-media-toast]');
  let toastTimer;
  const showToast=(message)=>{
    if(!toast) return;
    toast.textContent=message;
    toast.hidden=false;
    clearTimeout(toastTimer);
    toastTimer=setTimeout(()=>{toast.hidden=true;},2200);
  };
  const copyText=async(text)=>{
    try{
      await navigator.clipboard.writeText(text);
      showToast('Copied to clipboard');
    }catch(_error){
      const area=document.createElement('textarea');
      area.value=text;
      area.style.position='fixed';
      area.style.opacity='0';
      document.body.appendChild(area);
      area.select();
      document.execCommand('copy');
      area.remove();
      showToast('Copied to clipboard');
    }
  };

  const uploadPanel=root.querySelector('[data-upload-panel]');
  const fileInput=root.querySelector('[data-media-files]');
  const preview=root.querySelector('[data-upload-preview]');
  const dropzone=root.querySelector('[data-dropzone]');
  const uploadForm=root.querySelector('[data-upload-form]');
  const setUploadOpen=(open)=>{
    if(!uploadPanel) return;
    uploadPanel.classList.toggle('is-open',open);
    if(open) uploadPanel.scrollIntoView({behavior:'smooth',block:'center'});
  };
  root.querySelectorAll('[data-upload-toggle]').forEach(button=>button.addEventListener('click',()=>setUploadOpen(true)));
  root.querySelector('[data-upload-cancel]')?.addEventListener('click',()=>{
    setUploadOpen(false);
    if(uploadForm) uploadForm.reset();
    if(preview){preview.innerHTML='';preview.hidden=true;}
  });

  const renderFiles=()=>{
    if(!preview || !fileInput) return;
    const files=[...fileInput.files];
    preview.innerHTML='';
    preview.hidden=files.length===0;
    files.forEach((file,index)=>{
      const row=document.createElement('div');
      row.className='media-upload-file';
      const name=document.createElement('strong');
      name.textContent=file.name;
      name.title=file.name;
      const alt=document.createElement('input');
      alt.type='text';
      alt.name='alt_texts[]';
      alt.maxLength=500;
      alt.placeholder=files.length===1?'Alt text for this image':'Alt text for image '+(index+1)+' (optional)';
      alt.setAttribute('aria-label','Alt text for '+file.name);
      row.append(name,alt);
      preview.appendChild(row);
    });
  };
  fileInput?.addEventListener('change',renderFiles);
  if(dropzone && fileInput){
    ['dragenter','dragover'].forEach(type=>dropzone.addEventListener(type,event=>{
      event.preventDefault();
      dropzone.classList.add('is-dragging');
    }));
    ['dragleave','drop'].forEach(type=>dropzone.addEventListener(type,event=>{
      event.preventDefault();
      dropzone.classList.remove('is-dragging');
    }));
    dropzone.addEventListener('drop',event=>{
      const incoming=[...(event.dataTransfer?.files||[])].filter(file=>file.type.startsWith('image/'));
      if(!incoming.length) return;
      const transfer=new DataTransfer();
      incoming.forEach(file=>transfer.items.add(file));
      fileInput.files=transfer.files;
      renderFiles();
    });
  }

  const grid=root.querySelector('[data-media-grid]');
  const cards=grid?[...grid.querySelectorAll('[data-media-card]')]:[];
  const search=root.querySelector('[data-media-search]');
  const usageFilter=root.querySelector('[data-media-filter="usage"]');
  const altFilter=root.querySelector('[data-media-filter="alt"]');
  const typeFilter=root.querySelector('[data-media-filter="type"]');
  const sortSelect=root.querySelector('[data-media-sort]');
  const noResults=root.querySelector('[data-media-no-results]');
  const visibleCount=root.querySelector('[data-visible-count]');
  const selectVisible=root.querySelector('[data-select-visible]');

  const visibleCards=()=>cards.filter(card=>!card.hidden);
  const applyFilters=()=>{
    const term=(search?.value||'').trim().toLowerCase();
    const usage=usageFilter?.value||'all';
    const alt=altFilter?.value||'all';
    const type=typeFilter?.value||'all';
    let count=0;
    cards.forEach(card=>{
      const matchesTerm=!term||(card.dataset.search||'').includes(term);
      const matchesUsage=usage==='all'||card.dataset.usage===usage;
      const matchesAlt=alt==='all'||card.dataset.alt===alt;
      const matchesType=type==='all'||card.dataset.type===type;
      card.hidden=!(matchesTerm&&matchesUsage&&matchesAlt&&matchesType);
      if(!card.hidden) count++;
    });
    if(visibleCount) visibleCount.textContent=String(count);
    if(noResults) noResults.hidden=count!==0;
    if(selectVisible){
      const visibleChecks=visibleCards().map(card=>card.querySelector('[data-media-select]')).filter(Boolean);
      selectVisible.checked=visibleChecks.length>0&&visibleChecks.every(check=>check.checked);
      selectVisible.indeterminate=visibleChecks.some(check=>check.checked)&&!selectVisible.checked;
    }
  };
  [search,usageFilter,altFilter,typeFilter].forEach(control=>control?.addEventListener(control===search?'input':'change',applyFilters));

  const sortCards=()=>{
    if(!grid) return;
    const mode=sortSelect?.value||'newest';
    cards.sort((a,b)=>{
      if(mode==='oldest') return Number(a.dataset.created||0)-Number(b.dataset.created||0);
      if(mode==='name') return (a.dataset.name||'').localeCompare(b.dataset.name||'');
      if(mode==='largest') return Number(b.dataset.size||0)-Number(a.dataset.size||0);
      return Number(b.dataset.created||0)-Number(a.dataset.created||0);
    });
    cards.forEach(card=>grid.appendChild(card));
  };
  sortSelect?.addEventListener('change',sortCards);

  const setView=(view)=>{
    if(!grid) return;
    grid.classList.toggle('is-list',view==='list');
    root.querySelectorAll('[data-media-view]').forEach(button=>button.classList.toggle('is-active',button.dataset.mediaView===view));
    try{localStorage.setItem('mediapitch-media-view',view);}catch(_error){}
  };
  root.querySelectorAll('[data-media-view]').forEach(button=>button.addEventListener('click',()=>setView(button.dataset.mediaView||'grid')));
  try{setView(localStorage.getItem('mediapitch-media-view')==='list'?'list':'grid');}catch(_error){setView('grid');}

  root.querySelector('[data-clear-filters]')?.addEventListener('click',()=>{
    const params=new URLSearchParams(window.location.search);
    if(params.has('q')){
      window.location.href=window.location.pathname;
      return;
    }
    if(search) search.value='';
    if(usageFilter) usageFilter.value='all';
    if(altFilter) altFilter.value='all';
    if(typeFilter) typeFilter.value='all';
    applyFilters();
  });

  root.querySelectorAll('[data-copy-url]').forEach(button=>button.addEventListener('click',event=>{
    event.stopPropagation();
    const card=button.closest('[data-media-card]');
    if(card?.dataset.url) copyText(card.dataset.url);
  }));

  const bulkBar=root.querySelector('[data-bulk-bar]');
  const selectedCount=root.querySelector('[data-selected-count]');
  const allChecks=cards.map(card=>card.querySelector('[data-media-select]')).filter(Boolean);
  const refreshSelection=()=>{
    const selected=allChecks.filter(check=>check.checked);
    cards.forEach(card=>card.classList.toggle('is-selected',Boolean(card.querySelector('[data-media-select]')?.checked)));
    if(selectedCount) selectedCount.textContent=String(selected.length);
    if(bulkBar) bulkBar.hidden=selected.length===0;
    if(selectVisible){
      const checks=visibleCards().map(card=>card.querySelector('[data-media-select]')).filter(Boolean);
      selectVisible.checked=checks.length>0&&checks.every(check=>check.checked);
      selectVisible.indeterminate=checks.some(check=>check.checked)&&!selectVisible.checked;
    }
  };
  allChecks.forEach(check=>check.addEventListener('change',refreshSelection));
  selectVisible?.addEventListener('change',()=>{
    visibleCards().forEach(card=>{
      const check=card.querySelector('[data-media-select]');
      if(check) check.checked=selectVisible.checked;
    });
    refreshSelection();
  });
  root.querySelector('[data-clear-selection]')?.addEventListener('click',()=>{
    allChecks.forEach(check=>check.checked=false);
    if(selectVisible) selectVisible.checked=false;
    refreshSelection();
  });
  root.querySelector('[data-copy-selected]')?.addEventListener('click',()=>{
    const urls=cards.filter(card=>card.querySelector('[data-media-select]')?.checked).map(card=>card.dataset.url).filter(Boolean);
    if(urls.length) copyText(urls.join('\n'));
  });

  const dialog=root.querySelector('[data-media-dialog]');
  const dialogImage=root.querySelector('[data-dialog-image]');
  const dialogName=root.querySelector('[data-dialog-name]');
  const dialogMeta=root.querySelector('[data-dialog-meta]');
  const dialogUsage=root.querySelector('[data-dialog-usage]');
  const dialogUploader=root.querySelector('[data-dialog-uploader]');
  const dialogCreated=root.querySelector('[data-dialog-created]');
  const dialogUrl=root.querySelector('[data-dialog-url]');
  const dialogId=root.querySelector('[data-dialog-id]');
  const dialogAlt=root.querySelector('[data-dialog-alt]');
  const categoryUrl=root.querySelector('[data-dialog-category-url]');
  const deleteForm=root.querySelector('[data-dialog-delete]');
  const deleteId=root.querySelector('[data-dialog-delete-id]');
  const deleteNote=root.querySelector('[data-delete-note]');

  const openDetails=(card)=>{
    if(!dialog || !card) return;
    const url=card.dataset.url||'';
    const name=card.dataset.originalName||'Image';
    if(dialogImage){dialogImage.src=url;dialogImage.alt=card.dataset.altText||name;}
    if(dialogName) dialogName.textContent=name;
    if(dialogMeta) dialogMeta.textContent=(card.dataset.dimensions||'')+' · '+(card.dataset.fileSize||'')+(card.dataset.optimized==='1'?' · Optimized':'');
    if(dialogUsage) dialogUsage.textContent=card.dataset.usageLabel||'—';
    if(dialogUploader) dialogUploader.textContent=card.dataset.uploader||'—';
    if(dialogCreated){
      const raw=card.dataset.createdLabel||'';
      const date=raw?new Date(raw.replace(' ','T')):null;
      dialogCreated.textContent=date&&!Number.isNaN(date.getTime())?date.toLocaleString():raw||'—';
    }
    if(dialogUrl) dialogUrl.value=url;
    if(dialogId) dialogId.value=card.dataset.id||'';
    if(dialogAlt) dialogAlt.value=card.dataset.altText||'';
    if(categoryUrl) categoryUrl.value=url;
    if(deleteId) deleteId.value=card.dataset.id||'';
    if(deleteForm){
      const canDelete=card.dataset.canDelete==='1';
      const button=deleteForm.querySelector('button');
      if(button) button.hidden=!canDelete;
      if(deleteNote) deleteNote.textContent=canDelete?'This image is not referenced by CMS content.':'This image is in use and protected from deletion.';
    }
    if(typeof dialog.showModal==='function') dialog.showModal(); else dialog.setAttribute('open','');
  };
  root.querySelectorAll('[data-media-details]').forEach(button=>button.addEventListener('click',()=>openDetails(button.closest('[data-media-card]'))));
  root.querySelector('[data-dialog-close]')?.addEventListener('click',()=>dialog?.close());
  dialog?.addEventListener('click',event=>{
    if(event.target===dialog) dialog.close();
  });
  root.querySelector('[data-dialog-copy]')?.addEventListener('click',()=>{if(dialogUrl?.value) copyText(dialogUrl.value);});

  sortCards();
  applyFilters();
})();
