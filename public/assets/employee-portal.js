'use strict';
document.addEventListener('DOMContentLoaded',async()=>{
 const element=document.getElementById('employee-portal-state');
 if(!element)return;
 const state=JSON.parse(element.textContent);
 if(state.remind&&window.payrollFeedback){
  const change=await window.payrollFeedback.show({severity:'warning',title:'Ubah Password Awal',message:'Anda masih menggunakan password awal. Segera ubah password akun Anda.',confirmation:true,primaryLabel:'Ubah Sekarang',cancelLabel:'Nanti'});
  if(change){location.assign(state.passwordUrl);return;}
 }
});
