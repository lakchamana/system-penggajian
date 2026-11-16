'use strict';
(() => {
 const element=document.getElementById('employee-password-modal');
 if(!element||!window.bootstrap?.Modal)return;
 const modal=bootstrap.Modal.getOrCreateInstance(element);
 const result=document.getElementById('reveal-result');
 const output=document.getElementById('reveal-password-value');
 const error=document.getElementById('reveal-error');
 const loading=document.getElementById('reveal-loading');
 let request=null, generation=0;
 const clear=()=>{
  generation++;request?.abort();request=null;
  output.textContent='';result.hidden=true;error.hidden=true;error.textContent='';loading.hidden=true;
 };
 document.querySelectorAll('[data-reveal-account]').forEach(button=>button.addEventListener('click',async()=>{
  clear();
  document.getElementById('reveal-account-name').textContent=button.dataset.accountName;
  modal.show();
  request=new AbortController();const current=generation;loading.hidden=false;
  const body=new FormData();body.set('id',button.dataset.revealAccount);body.set('_csrf',document.querySelector('meta[name=csrf-token]').content);
  try{
   const response=await fetch(element.dataset.passwordEndpoint,{method:'POST',body,signal:request.signal,cache:'no-store',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
   const data=await response.json();
   if(current!==generation)return;
   if(!response.ok)throw new Error(data.error||'Password belum dapat dibuka.');
   output.textContent=data.password;delete data.password;result.hidden=false;
  }catch(exception){
   if(current===generation&&exception.name!=='AbortError'){error.textContent=exception.message||'Password belum dapat dibuka. Coba kembali.';error.hidden=false;}
  }finally{if(current===generation){request=null;loading.hidden=true;}}
 }));
 element.addEventListener('shown.bs.modal',()=>element.querySelector('.modal-footer button')?.focus());
 element.addEventListener('hide.bs.modal',clear);
 document.addEventListener('visibilitychange',()=>{if(document.hidden){clear();modal.hide();}});
 window.addEventListener('pagehide',clear);
})();
