document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('input[type="password"]').forEach(input=>{
    if(input.dataset.passwordToggle==='1')return;
    input.dataset.passwordToggle='1';
    const wrap=document.createElement('span');wrap.className='password-field';
    input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);
    const button=document.createElement('button');button.type='button';button.className='password-eye';button.setAttribute('aria-label','Show password');button.setAttribute('title','Show password for 3 seconds');button.textContent='👁';
    let timer=null;
    const hide=()=>{input.type='password';button.setAttribute('aria-label','Show password');button.textContent='👁';if(timer){clearTimeout(timer);timer=null;}};
    button.addEventListener('click',()=>{if(input.type==='text'){hide();return;}input.type='text';button.setAttribute('aria-label','Hide password');button.textContent='🙈';if(timer)clearTimeout(timer);timer=setTimeout(hide,3000);});
    input.addEventListener('blur',()=>{if(input.type==='text')hide();});
    wrap.appendChild(button);
  });
});
