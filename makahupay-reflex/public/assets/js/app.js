(()=>{
  const toggle=document.getElementById('sidebarToggle');
  const sidebar=document.getElementById('sidebar');
  if(toggle&&sidebar) toggle.addEventListener('click',()=>sidebar.classList.toggle('open'));
  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm||'Are you sure?'))e.preventDefault()}));
  document.querySelectorAll('[data-search-target]').forEach(input=>{
    const target=document.querySelector(input.dataset.searchTarget); if(!target)return;
    input.addEventListener('input',()=>{const term=input.value.toLowerCase().trim();target.querySelectorAll('tbody tr').forEach(row=>{row.hidden=!row.innerText.toLowerCase().includes(term)})});
  });
  const service=document.querySelector('[data-price-source]');
  const qty=document.querySelector('[data-qty]');
  const discount=document.querySelector('[data-discount]');
  const received=document.querySelector('[data-received]');
  const subtotalOut=document.querySelector('[data-subtotal-out]');
  const totalOut=document.querySelector('[data-total-out]');
  const changeOut=document.querySelector('[data-change-out]');
  const fmt=n=>'₱'+Number(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
  function calc(){if(!service)return;const price=Number(service.selectedOptions[0]?.dataset.price||0),q=Math.max(1,Number(qty?.value||1)),sub=price*q,disc=Math.max(0,Number(discount?.value||0)),total=Math.max(0,sub-disc),pay=Number(received?.value||0);document.querySelectorAll('[data-subtotal-out]').forEach(el=>el.textContent=fmt(sub));document.querySelectorAll('[data-total-out]').forEach(el=>el.textContent=fmt(total));if(changeOut)changeOut.textContent=fmt(Math.max(0,pay-total));const hidden=document.querySelector('[data-total-hidden]');if(hidden)hidden.value=total.toFixed(2)}
  [service,qty,discount,received].forEach(el=>el&&el.addEventListener('input',calc));calc();
  document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const buttons=form.querySelectorAll('button[type="submit"]');buttons.forEach(b=>{if(!b.dataset.noBusy){b.dataset.originalText=b.innerHTML;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving…'}})}));
})();
