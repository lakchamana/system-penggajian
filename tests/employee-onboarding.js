'use strict';
const {expect}=require('@playwright/test');
const path=require('node:path');

// Called only by the employee suite using its owned temporary database and synthetic key.
module.exports=async function onboarding({admin,base,newPage,fixture,output,start,extraServers,freePort,ready,login,later,changePassword,closeFeedback,noOverflow,csrf}){
 const snapshot=()=>JSON.parse(fixture('status'));
 const payload=(nip,name='QA Karyawan Otomatis')=>({id:'0',nip,nama:name,kd_bagian:'DMY',gapok:'3250000',t_jabatan:'0',t_harian:'0',t_lembur:'0'});
 const save=(page,address,data,token)=>page.request.post(address+'/master/karyawan',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:token,...data}});
 const fill=async(page,data)=>{
  await page.locator('#master-nip').fill(data.nip);await page.locator('#master-nama').fill(data.nama);
  await page.locator('#master-kd_bagian-toggle').click();await page.locator('#master-kd_bagian-options [data-select-value="DMY"]').click();
  await page.locator('#master-gapok').fill(data.gapok);
 };
 const unchanged=(before,after,nip)=>{
  expect(after.employees.find(row=>row.nip===nip)).toBeUndefined();expect(after.accounts.find(row=>row.username===nip)).toBeUndefined();
  expect(after.account_fingerprints).toEqual(before.account_fingerprints);expect(after.creation_audit).toEqual(before.creation_audit);
 };
 await admin.goto(base+'/master/karyawan');
 await admin.locator('.page-heading').getByRole('link',{name:'Tambah Karyawan',exact:true}).click();
 await expect(admin).toHaveURL(base+'/master/karyawan?new=1');
 await admin.goto(base+'/akun-karyawan');
 await expect(admin.locator('.sidebar-note')).toHaveCount(0);
 await admin.getByRole('link',{name:'Tambah Karyawan',exact:true}).click();await expect(admin).toHaveURL(base+'/master/karyawan?new=1');
 await expect(admin.getByText('Karyawan masuk dengan NIP.',{exact:true})).toBeVisible();
 await expect(admin.locator('.master-form-panel .form-note')).toHaveCount(0);
 await expect(admin.locator('.master-form-panel input[name=username],.master-form-panel input[type=password]')).toHaveCount(0);
 for(const width of [1440,1024,390,360]){
  await admin.setViewportSize({width,height:1000});
  if(width<=760)await expect.poll(()=>admin.locator('#sidebar').evaluate(sidebar=>sidebar.getBoundingClientRect().right)).toBeLessThanOrEqual(1);
  await noOverflow(admin);
  await admin.screenshot({path:path.join(output,`add-employee-${width}.png`),fullPage:true});
 }
 await admin.setViewportSize({width:1440,height:1000});const data=payload('00077');await fill(admin,data);
 const before=snapshot();await admin.getByRole('button',{name:'Simpan Karyawan & Akun',exact:true}).click();await expect(admin).toHaveURL(base+'/master/karyawan');
 await expect(admin.locator('#feedback-modal .feedback-message')).toContainText('Karyawan dan akun sudah dibuat.');await closeFeedback(admin);
 const created=snapshot(),employee=created.employees.find(row=>row.nip===data.nip),account=created.accounts.find(row=>row.username===data.nip);
 expect(employee).toBeDefined();expect(Number(account.employee_id)).toBe(Number(employee.id));expect(Number(account.is_active)).toBe(1);expect(Number(account.must_change_password)).toBe(1);expect(created.slips.some(row=>row.nip===data.nip)).toBe(false);
 expect(created.accounts.length).toBe(before.accounts.length+1);expect(created.creation_audit.length).toBe(before.creation_audit.length+2);
 await admin.goto(base+'/akun-karyawan?q=00077');await admin.getByRole('button',{name:'Lihat Password',exact:true}).click();await expect(admin.locator('#reveal-password-value')).toHaveText('Mutiara@2026!');
 await admin.locator('#employee-password-modal .modal-footer button').click();await expect(admin.locator('#employee-password-modal')).toBeHidden();
 const token=await csrf(admin),child=await newPage();await login(child,base,'00077','Mutiara@2026!');await expect(child).toHaveURL(base+'/karyawan/slip');await later(child);
 await expect(child.locator('.employee-slip-placeholder')).toContainText('belum tersedia.');await expect(child.locator('.sidebar-note')).toHaveCount(0);
 expect((await save(child,base,payload('00078'),await csrf(child))).status()).toBe(403);
 const badCsrfBefore=snapshot();expect((await save(admin,base,payload('00078'),'bad')).status()).toBe(419);unchanged(badCsrfBefore,snapshot(),'00078');
 const duplicateBefore=snapshot();expect((await save(admin,base,data,token)).status()).toBe(422);expect(snapshot().account_fingerprints).toEqual(duplicateBefore.account_fingerprints);expect(snapshot().employees).toEqual(duplicateBefore.employees);
 fixture('username_collision');const collisionBefore=snapshot();expect((await save(admin,base,payload('QACOLLISION'),token)).status()).toBe(422);
 const collisionAfter=snapshot();expect(collisionAfter.employees.find(row=>row.nip==='QACOLLISION')).toBeUndefined();expect(collisionAfter.account_fingerprints).toEqual(collisionBefore.account_fingerprints);expect(collisionAfter.creation_audit).toEqual(collisionBefore.creation_audit);
 fixture('fail_account_audit');
 try{const auditBefore=snapshot();expect((await save(admin,base,payload('00079'),token)).status()).toBe(422);unchanged(auditBefore,snapshot(),'00079');}finally{fixture('restore_account_audit');}
 // Editing preserves both personal credentials and the current employee session.
 await child.goto(base+'/karyawan/ubah-password');await expect(child.locator('.sidebar-note')).toHaveCount(0);await changePassword(child,'Mutiara@2026!','Pribadi77!');await expect(child).toHaveURL(base+'/karyawan/slip');await closeFeedback(child);
 const personal=snapshot();expect((await save(admin,base,{...data,id:String(employee.id),nama:'QA Nama Diperbarui'},token)).ok()).toBe(true);
 expect(snapshot().account_fingerprints).toEqual(personal.account_fingerprints);await child.goto(base+'/karyawan/slip');await expect(child).toHaveURL(base+'/karyawan/slip');
 await admin.request.post(base+'/akun-karyawan',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:token,action:'set_active',id:account.id,is_active:'0'}});
 const inactive=snapshot();expect((await save(admin,base,{...data,id:String(employee.id)},token)).ok()).toBe(true);expect(snapshot().account_fingerprints).toEqual(inactive.account_fingerprints);
 const legacy=inactive.employees.find(row=>row.nip==='00042');expect((await save(admin,base,{...payload('00042',legacy.nama),id:String(legacy.id)},token)).ok()).toBe(true);expect(snapshot().accounts.some(row=>Number(row.employee_id)===Number(legacy.id))).toBe(false);
 // Exercise real HTTP requests with unavailable/invalid encryption and initial-password configuration.
 for(const [index,overrides] of [{EMPLOYEE_PASSWORD_KEY:' '},{EMPLOYEE_PASSWORD_KEY:'invalid'},{EMPLOYEE_INITIAL_PASSWORD:' '}].entries()){
  const port=await freePort(),address=`http://127.0.0.1:${port}/`+new URL(base).pathname.split('/').filter(Boolean).join('/');
  const server=start(port,path.resolve(__dirname,'../..'),overrides);extraServers.push(server);await ready(address);
  const failing=await newPage();await login(failing,address,'qa_employee_admin','Qa!Portal-2026-Admin');await expect(failing).toHaveURL(address+'/');await failing.goto(address+'/master/karyawan?new=1');
  const failedData=payload('0090'+index,'QA Penyimpanan Dibatalkan');await fill(failing,failedData);const failureBefore=snapshot();
  const saveButton=failing.getByRole('button',{name:'Simpan Karyawan & Akun',exact:true});
  const feedback=failing.locator('#feedback-modal');
  await saveButton.click();
  await expect(feedback.locator('.feedback-message')).toHaveText('Karyawan belum tersimpan. Hubungi pengelola aplikasi.');
  await expect(feedback.locator('[data-feedback-issues] li')).toHaveCount(0);
  await expect(feedback.getByRole('button',{name:'Tutup',exact:true})).toBeFocused();
  await failing.keyboard.press('Escape');
  await expect(feedback).toBeHidden();
  await expect(saveButton).toBeFocused();
  await expect(failing.locator('#master-nip')).toHaveValue(failedData.nip);await expect(failing.locator('#master-nip')).not.toHaveAttribute('readonly');await expect(failing.locator('#master-nama')).toHaveValue(failedData.nama);unchanged(failureBefore,snapshot(),failedData.nip);
  const response=await failing.request.post(address+'/master/karyawan',{form:{_csrf:await csrf(failing),...failedData}});expect(response.status()).toBe(503);
  const mode=await failing.evaluate(html=>{
   const doc=new DOMParser().parseFromString(html,'text/html');
   return {
    id:doc.querySelector('.master-form-panel input[name=id]').value,
    title:doc.querySelector('.master-form-panel h2').textContent,
    message:doc.querySelector('.alert-danger[role=alert]').textContent,
    modalEvents:JSON.parse(doc.querySelector('#feedback-events').textContent),
   };
  },await response.text());
  expect(mode).toEqual({id:'0',title:'Tambah Karyawan',message:'Karyawan belum tersimpan. Hubungi pengelola aplikasi.',modalEvents:[]});
  unchanged(failureBefore,snapshot(),failedData.nip);
 }
 console.log('PASS: one-form employee/account creation; leading zeroes; credentials and reminder; no payroll generated; duplicate/CSRF/role rejection; atomic rollback including audit failure; editing preserves password/status/sessions; no bulk accounts; desktop/mobile forms.');
};
