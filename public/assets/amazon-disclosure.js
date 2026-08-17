document.addEventListener('DOMContentLoaded',()=>{
  const target=document.getElementById('amazon-price-disclosure');
  if(!target)return;
  document.querySelectorAll('.price').forEach(price=>{
    if(price.querySelector('.amazon-price-details'))return;
    const link=document.createElement('a');
    link.href='#amazon-price-disclosure';
    link.className='amazon-price-details';
    link.textContent='Details';
    link.style.marginLeft='.45em';
    link.style.fontSize='.75em';
    link.addEventListener('click',()=>{target.setAttribute('tabindex','-1');setTimeout(()=>target.focus(),0);});
    price.appendChild(link);
  });
});
