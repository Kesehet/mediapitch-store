document.addEventListener('DOMContentLoaded',()=>{
  const form=document.getElementById('guide-form');if(!form)return;
  const body=form.querySelector('textarea[name="body"]');if(!body)return;
  const box=document.createElement('div');box.className='panel';box.style.marginBottom='1rem';
  box.innerHTML='<div class="panel-head"><div><h2>Guide structure</h2><p class="muted">Insert consistent editorial sections. Headings automatically become the public table of contents.</p></div></div><div class="form-actions" style="justify-content:flex-start"><button type="button" class="secondary-button" data-guide-insert="selection">+ How we selected</button><button type="button" class="secondary-button" data-guide-insert="faq">+ FAQ question</button></div>';
  body.closest('label').before(box);
  const insert=text=>{const start=body.selectionStart||body.value.length;const before=body.value.slice(0,start);const after=body.value.slice(body.selectionEnd||start);const prefix=before&& !before.endsWith('\n')?'\n\n':'';body.value=before+prefix+text+'\n\n'+after;body.focus();body.selectionStart=body.selectionEnd=(before+prefix+text+'\n\n').length;};
  box.addEventListener('click',e=>{const b=e.target.closest('[data-guide-insert]');if(!b)return;
    if(b.dataset.guideInsert==='selection')insert('<h2>How we selected these products</h2>\n<p>Explain the criteria, research process, testing approach and editorial considerations used for this guide.</p>');
    else insert('<h2>Frequently asked questions</h2>\n<h3>Question goes here?</h3>\n<p>Answer goes here.</p>');
  });
});
