document.addEventListener('DOMContentLoaded',()=>{
  const slugify=value=>String(value||'').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');

  document.querySelectorAll('form.form-panel').forEach(form=>{
    let dirty=false;
    let submitting=false;
    const actions=form.querySelector('.form-actions');
    let state=null;

    if(actions){
      state=document.createElement('span');
      state.className='form-save-state';
      state.textContent='All changes saved';
      actions.prepend(state);
    }

    const markDirty=()=>{
      if(submitting)return;
      dirty=true;
      if(state){state.textContent='Unsaved changes';state.classList.add('dirty');}
    };

    form.addEventListener('input',markDirty,true);
    form.addEventListener('change',markDirty,true);
    form.addEventListener('submit',()=>{
      submitting=true;
      dirty=false;
      if(state){state.textContent='Saving…';state.classList.remove('dirty');}
    });

    const title=form.querySelector('input[name="title"]');
    const slug=form.querySelector('input[name="slug"]');
    if(title&&slug){
      let lastGenerated='';
      const initialSlug=slug.value.trim();
      const syncSlug=()=>{
        const generated=slugify(title.value);
        if(!slug.value.trim()||slug.value===lastGenerated){slug.value=generated;lastGenerated=generated;}
      };
      if(!initialSlug){syncSlug();}
      title.addEventListener('input',syncSlug);
      slug.addEventListener('input',()=>{if(slug.value!==lastGenerated)lastGenerated='';});
    }

    window.addEventListener('beforeunload',event=>{
      if(!dirty||submitting)return;
      event.preventDefault();
      event.returnValue='';
    });

    document.addEventListener('keydown',event=>{
      if(!(event.ctrlKey||event.metaKey)||event.key.toLowerCase()!=='s')return;
      if(!document.body.contains(form)||!form.offsetParent)return;
      event.preventDefault();
      const active=document.activeElement;
      if(active&&typeof active.blur==='function')active.blur();
      if(typeof form.requestSubmit==='function')form.requestSubmit();else form.submit();
    });
  });

  document.querySelectorAll('.data-table,.admin-table').forEach(table=>{
    const body=table.tBodies[0];
    if(!body)return;
    const rows=[...body.rows].filter(row=>!row.querySelector('.empty'));
    if(rows.length<6)return;
    const panel=table.closest('.panel');
    if(!panel||panel.querySelector('.table-filter'))return;

    const wrap=document.createElement('div');
    wrap.className='table-filter';
    const input=document.createElement('input');
    input.type='search';
    input.placeholder='Filter this list…';
    input.setAttribute('aria-label','Filter this list');
    const count=document.createElement('span');
    count.textContent=rows.length+' items';
    wrap.append(input,count);

    const tableWrap=table.closest('.table-wrap')||table;
    tableWrap.parentNode.insertBefore(wrap,tableWrap);

    input.addEventListener('input',()=>{
      const query=input.value.trim().toLowerCase();
      let visible=0;
      rows.forEach(row=>{
        const show=!query||row.textContent.toLowerCase().includes(query);
        row.style.display=show?'':'none';
        if(show)visible++;
      });
      count.textContent=visible+' of '+rows.length+' items';
    });
  });
});
