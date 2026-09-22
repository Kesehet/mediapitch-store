document.addEventListener('DOMContentLoaded',()=>{
  const forms=[...document.querySelectorAll('form.form-panel, form.admin-form, form.stack-form')]
    .filter(form=>form.method.toLowerCase()==='post' && form.querySelector('textarea, input[name="title"], input[name="name"]'));
  if(!forms.length)return;

  const csrf=form=>form.querySelector('input[name="_csrf"]')?.value||'';
  const keyFor=form=>{
    const id=form.querySelector('input[name="id"]')?.value||'new';
    const action=new URL(form.action,location.origin).pathname;
    return ('v2:'+action+':'+id).slice(0,190);
  };
  const serialize=form=>{
    const data={};
    for(const [name,value] of new FormData(form).entries()){
      if(name==='_csrf'||name==='_draft_key')continue;
      if(Object.prototype.hasOwnProperty.call(data,name)){
        if(!Array.isArray(data[name]))data[name]=[data[name]];
        data[name].push(String(value));
      }else data[name]=String(value);
    }
    return {version:2,savedAt:Date.now(),path:location.pathname,fields:data};
  };
  const setField=(form,name,value)=>{
    const controls=[...form.querySelectorAll('[name="'+CSS.escape(name)+'"]')];
    if(!controls.length)return;
    const values=Array.isArray(value)?value:[value];
    if(controls.length>1){
      controls.forEach((control,i)=>{
        const v=values[i]??'';
        if(control.type==='checkbox'||control.type==='radio')control.checked=values.includes(control.value);
        else control.value=v;
        control.dispatchEvent(new Event('change',{bubbles:true}));
      });
      return;
    }
    const control=controls[0];
    if(control.type==='checkbox'||control.type==='radio')control.checked=values.includes(control.value);
    else control.value=values[0]??'';
    control.dispatchEvent(new Event('change',{bubbles:true}));
  };
  const restore=(form,payload)=>{
    if(!payload?.fields)return;

    const repeated=['product_title[]','product_id[]','rank_position[]','score[]','product_best_for[]','recommendation[]','cta_text[]'];
    const desired=Math.max(0,...repeated.map(name=>Array.isArray(payload.fields[name])?payload.fields[name].length:0));
    const addButton=form.querySelector('#add-product')||document.getElementById('add-product');
    if(desired>0&&addButton){
      let current=Math.max(
        form.querySelectorAll('[name="product_title[]"]').length,
        form.querySelectorAll('[name="product_id[]"]').length
      );
      while(current<desired){addButton.click();current++;}
    }

    Object.entries(payload.fields).forEach(([name,value])=>setField(form,name,value));
    form.dispatchEvent(new CustomEvent('mediapitch:draft-restored',{bubbles:true}));
  };
  const signature=payload=>JSON.stringify(payload?.fields||{});
  const post=(url,body)=>{
    const fd=new FormData();
    Object.entries(body).forEach(([k,v])=>fd.append(k,String(v)));
    return fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
  };

  forms.forEach(form=>{
    const key=keyFor(form);
    const hidden=document.createElement('input');
    hidden.type='hidden';hidden.name='_draft_key';hidden.value=key;form.append(hidden);
    const localKey='mediapitch:form-draft:'+key;
    let timer=null,lastServerSave=0;

    const saveLocal=()=>{
      try{localStorage.setItem(localKey,JSON.stringify(serialize(form)));}catch(_){}
    };
    const saveServer=async()=>{
      const now=Date.now();
      if(now-lastServerSave<4000)return;
      lastServerSave=now;
      const payload=serialize(form);
      try{
        await post('/admin/form-drafts/save',{_csrf:csrf(form),key,payload:JSON.stringify(payload)});
      }catch(_){}
    };
    const schedule=()=>{
      clearTimeout(timer);
      timer=setTimeout(()=>{saveLocal();saveServer();},650);
    };
    form.addEventListener('input',schedule);
    form.addEventListener('change',schedule);
    window.addEventListener('beforeunload',saveLocal);
    form.addEventListener('submit',()=>{saveLocal();});

    const candidates=[];
    try{
      const local=JSON.parse(localStorage.getItem(localKey)||'null');
      if(local)candidates.push({source:'browser',payload:local,updatedAt:local.savedAt||0});
    }catch(_){}

    fetch('/admin/form-drafts/load?key='+encodeURIComponent(key),{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r=>r.ok?r.json():null)
      .then(data=>{
        if(data?.draft?.payload){
          const stamp=Date.parse(data.draft.updated_at||'')||data.draft.payload.savedAt||0;
          candidates.push({source:'server',payload:data.draft.payload,updatedAt:stamp});
        }
        const current=serialize(form);
        const best=candidates.sort((a,b)=>b.updatedAt-a.updatedAt)[0];
        if(!best||signature(best.payload)===signature(current))return;

        const banner=document.createElement('div');
        banner.className='flash';
        banner.style.marginBottom='1rem';
        const when=best.updatedAt?new Date(best.updatedAt).toLocaleString():'an earlier session';
        banner.innerHTML='<strong>Recoverable unsaved draft found.</strong> A '+best.source+' backup from '+when+' is available. '+
          '<button type="button" class="secondary-button" data-draft-restore>Restore</button> '+
          '<button type="button" class="secondary-button" data-draft-dismiss>Dismiss</button>';
        form.before(banner);
        banner.querySelector('[data-draft-restore]').addEventListener('click',()=>{
          restore(form,best.payload);saveLocal();saveServer();banner.remove();
        });
        banner.querySelector('[data-draft-dismiss]').addEventListener('click',()=>{
          try{localStorage.removeItem(localKey);}catch(_){}
          post('/admin/form-drafts/delete',{_csrf:csrf(form),key}).catch(()=>{});
          banner.remove();
        });
      }).catch(()=>{});

    setInterval(()=>{saveLocal();saveServer();},15000);
  });
});
