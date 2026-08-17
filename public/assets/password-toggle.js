document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('input[type="password"]').forEach(input=>{
    if(input.dataset.passwordToggle==='1')return;
    input.dataset.passwordToggle='1';
    const wrap=document.createElement('span');wrap.style.position='relative';wrap.style.display='block';
    input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);input.style.paddingRight='42px';
    const button=document.createElement('button');button.type='button';button.setAttribute('aria-label','Show password');button.setAttribute('title','Show password for 3 seconds');button.textContent='👁';
    Object.assign(button.style,{position:'absolute',right:'8px',top:'50%',transform:'translateY(-50%)',border:'0',background:'transparent',cursor:'pointer',padding:'4px',fontSize:'16px',lineHeight:'1'});
    let timer=null;
    const hide=()=>{input.type='password';button.setAttribute('aria-label','Show password');button.textContent='👁';if(timer){clearTimeout(timer);timer=null;}};
    button.addEventListener('click',()=>{if(input.type==='text'){hide();return;}input.type='text';button.setAttribute('aria-label','Hide password');button.textContent='🙈';if(timer)clearTimeout(timer);timer=setTimeout(hide,3000);});
    input.addEventListener('blur',()=>{if(input.type==='text')hide();});
    wrap.appendChild(button);
  });
});