'use strict';
const {chromium,expect}=require('@playwright/test');
const {spawn,execFileSync}=require('node:child_process');
const {randomBytes,createHash}=require('node:crypto');
const net=require('node:net'),path=require('node:path'),fs=require('node:fs');
const root=path.resolve(__dirname,'..'),database='penggajian_employee_test_'+randomBytes(4).toString('hex');
const php=process.env.PHP_PATH||'php';
const fixture=command=>execFileSync(php,['tests/employee-portal-fixtures.php',database,command],{cwd:root,encoding:'utf8',windowsHide:true});
const initial='Mutiara@2026!',adminPassword='Qa!Portal-2026-Admin',output=path.join(root,'storage/qa/employee-portal');
const personal=['Baru2026','Kedua888','Ketiga88'];
async function freePort(){const server=net.createServer();await new Promise(r=>server.listen(0,'127.0.0.1',r));const port=server.address().port;await new Promise(r=>server.close(r));return port;}
async function ready(base){for(let i=0;i<80;i++){try{if((await fetch(base+'/login')).ok)return;}catch{}await new Promise(r=>setTimeout(r,100));}throw new Error('Server uji belum siap.');}
async function closeFeedback(page){await page.waitForLoadState('load');const modal=page.locator('#feedback-modal'),events=page.locator('#feedback-events');const count=await events.count()?JSON.parse(await events.textContent()).length:0;for(let i=0;i<count;i++){await expect(modal).toBeVisible();await page.locator('#feedback-accept').click();}if(!count&&await modal.isVisible())await page.locator('#feedback-accept').click();await expect(modal).toBeHidden();await expect(page.locator('.modal-backdrop')).toHaveCount(0);}
async function later(page){await expect(page.locator('#feedback-title')).toHaveText('Ubah Password Awal');await page.getByRole('button',{name:'Nanti',exact:true}).click();await expect(page.locator('#feedback-modal')).toBeHidden();await expect(page.locator('.modal-backdrop')).toHaveCount(0);}
async function login(page,base,username,password){await page.goto(base+'/login');await closeFeedback(page);await page.getByLabel('ID pengguna / Nomor induk',{exact:true}).fill(username);await page.getByLabel('Kata sandi',{exact:true}).fill(password);await page.getByRole('button',{name:'Masuk ke ruang kerja'}).click();}
async function changePassword(page,current,password){await closeFeedback(page);await page.getByLabel('Kata sandi saat ini',{exact:true}).fill(current);await page.getByLabel('Kata sandi baru',{exact:true}).fill(password);await page.getByLabel('Konfirmasi kata sandi baru',{exact:true}).fill(password);await page.getByRole('button',{name:'Simpan Password Baru'}).click();}
async function noOverflow(page){expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);}
async function csrf(page){return page.locator('meta[name=csrf-token]').getAttribute('content');}
async function downloadPdf(page,filename){
 const downloaded=page.waitForEvent('download');await page.getByRole('link',{name:'Unduh PDF',exact:true}).click();const file=await downloaded;
 expect(file.suggestedFilename()).toMatch(/^slip-gaji-\d{4}-\d{2}\.pdf$/);await file.saveAs(path.join(output,filename));
 expect(fs.readFileSync(path.join(output,filename)).subarray(0,5).toString()).toBe('%PDF-');
}
async function choose(page,id,value){
 const navigation=page.waitForNavigation();
 await page.locator('#'+id+'-toggle').click();await page.locator('#'+id+'-options [data-select-value="'+value+'"]').click();
 await navigation;await expect(page.locator('#'+id)).toHaveValue(String(value));
 expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);
}
(async()=>{
 let server,rootServer,browser,created=false;
 const errors=[],extraServers=[];
 try{
  const state=JSON.parse(fixture('create'));created=true;console.log(fixture('unit'));
  const port=await freePort(),origin=`http://127.0.0.1:${port}`,base=origin+'/'+path.basename(root);
  const testEnv={...process.env,DB_DATABASE:database,EMPLOYEE_PASSWORD_KEY:createHash('sha256').update('employee-portal-synthetic-test-key').digest('base64'),EMPLOYEE_INITIAL_PASSWORD:initial};
  const start=(port,documentRoot,overrides={})=>spawn(php,['-d','variables_order=EGPCS','-S',`127.0.0.1:${port}`,'-t',documentRoot,path.join(root,'scripts/router.php')],{cwd:root,env:{...testEnv,...overrides},stdio:'ignore',windowsHide:true});
  server=start(port,path.dirname(root));await ready(base);
  browser=await chromium.launch({channel:'chrome',headless:true});fs.mkdirSync(output,{recursive:true});
  const newPage=async(width=1440)=>{const context=await browser.newContext({viewport:{width,height:1000}});await context.addInitScript(()=>{window.employeePrintCalls=0;window.print=()=>{window.employeePrintCalls++;};});const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));return page;};
  const pages=[];
  for(let i=1;i<=3;i++){
   const page=await newPage();pages.push(page);await login(page,base,`DUMMY00${i}`,initial);await expect(page).toHaveURL(base+'/karyawan/slip');
   await expect(page.locator('#feedback-title')).toHaveText('Ubah Password Awal');
   if(i===1){await page.setViewportSize({width:360,height:1000});await noOverflow(page);await page.screenshot({path:path.join(output,'reminder-360.png')});await page.setViewportSize({width:1440,height:1000});}
   await later(page);await page.goto(base+'/karyawan/slip');await page.waitForLoadState('load');expect(JSON.parse(await page.locator('#employee-portal-state').textContent()).remind).toBe(false);await expect(page.locator('#feedback-modal')).toBeHidden();
   await expect(page.locator('.page-heading,.employee-welcome,.employee-summary,.employee-slip-list,.employee-notice,.employee-slip-screen,.sidebar-note,.page-footer')).toHaveCount(0);
   await expect(page.getByRole('button',{name:'Tampilkan',exact:true})).toHaveCount(0);await expect(page.getByRole('link',{name:/Lihat Slip|Periode terbaru/})).toHaveCount(0);
   await expect(page.locator('script[src*="chart.umd"]')).toHaveCount(0);await expect(page.locator('#slip-month')).toHaveValue(String(state.current_month));await expect(page.locator('#slip-year')).toHaveValue(String(state.current_year));await expect(page.locator('#slip-month option[value=""],#slip-year option[value=""]')).toHaveCount(0);
   const currentSlip=state.slips.some(row=>row.nip===`DUMMY00${i}`&&Number(row.bulan)===state.current_month&&Number(row.tahun)===state.current_year);
   await expect(page.locator('[data-print]')).toBeEnabled({enabled:currentSlip});expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);
   if(currentSlip)await expect(page.locator('.employee-print-document')).toBeVisible();else await expect(page.locator('.employee-slip-placeholder')).toContainText('belum tersedia.');
   await page.goto(base+'/karyawan/slip?bulan=10&tahun=2026');await expect(page.locator('.employee-print-slip')).toHaveCount(1);await expect(page.locator('.employee-print-document')).toBeVisible();expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);
   await page.getByRole('button',{name:'Cetak',exact:true}).click();expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(1);
   if(i===1){await page.pdf({path:path.join(output,'employee-slip-a4.pdf'),preferCSSPageSize:true,printBackground:true});await downloadPdf(page,'employee-slip-download.pdf');await page.evaluate(()=>dispatchEvent(new Event('afterprint')));await expect(page.locator('.employee-print-document')).toBeVisible();}
   await page.goto(base+'/karyawan/ubah-password');await expect(page.locator('.employee-notice,.sidebar-note')).toHaveCount(0);await expect(page.getByLabel('Kata sandi baru',{exact:true})).toHaveAttribute('minlength','8');
   if(i===1){
    const token=await csrf(page);const post=(password,confirmation=password)=>page.request.post(base+'/karyawan/ubah-password',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:token,current_password:initial,new_password:password,password_confirmation:confirmation}});
    expect((await post('Baru202')).status()).toBe(422);expect((await post(initial)).status()).toBe(422);expect((await post('Baru2026','Berbeda8')).status()).toBe(422);
    const input=page.getByLabel('Kata sandi baru',{exact:true});await input.fill('Baru202');expect(await input.evaluate(e=>e.validity.valid)).toBe(false);await input.fill('Baru2026');expect(await input.evaluate(e=>e.validity.valid)).toBe(true);
    const missing=await page.request.post(base+'/karyawan/ubah-password',{form:{current_password:initial,new_password:'Baru2026',password_confirmation:'Baru2026'}});expect(missing.status()).toBe(419);
   }
   await changePassword(page,initial,personal[i-1]);await expect(page).toHaveURL(base+'/karyawan/slip');await closeFeedback(page);expect(JSON.parse(await page.locator('#employee-portal-state').textContent()).remind).toBe(false);
  }
  console.log('PASS: initial login opens slip; dismissible per-session reminder; minimal page; inline preview; manual print; 7 rejected and 8 accepted; password encryption preserved.');
  const page=pages[0],first=state.slips.find(s=>s.nip==='DUMMY001'),other=state.slips.find(s=>s.nip==='DUMMY002');
  const old=await newPage();await login(old,base,'DUMMY001',personal[0]);await expect(old).toHaveURL(base+'/karyawan/slip');await old.waitForLoadState('load');await expect(old.locator('#feedback-modal')).toBeHidden();
  await page.goto(base+'/karyawan/ubah-password');await changePassword(page,personal[0],'Secret88');await expect(page).toHaveURL(base+'/karyawan/slip');await closeFeedback(page);
  await old.goto(base+'/karyawan/slip');await expect(old).toHaveURL(base+'/login');await login(old,base,'DUMMY001','Secret88');await expect(old).toHaveURL(base+'/karyawan/slip');await old.waitForLoadState('load');await expect(old.locator('#feedback-modal')).toBeHidden();fixture('seed');
  const token=await csrf(page);
  for(const route of ['/laporan?view=1','/master/karyawan','/migrasi','/arsip','/profil','/transaksi','/akun-karyawan','/api/karyawan','/api/bagian'])expect((await page.request.get(base+route)).status(),route).toBe(403);
  for(const route of ['/api/hitung','/transaksi','/akun-karyawan/password'])expect((await page.request.post(base+route,{form:{_csrf:token}})).status(),route).toBe(403);
  expect((await page.request.get(base+'/karyawan/slip/detail?id='+other.id)).status()).toBe(404);
  await page.goto(base+'/karyawan/slip/detail?id='+first.id);await expect(page).toHaveURL(base+'/karyawan/slip?bulan=10&tahun=2026');expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);await expect(page.locator('[data-slip-field=tbersih]')).toHaveText('3.000.000');
  await page.goto(base+'/karyawan/slip?bulan=1&tahun=2026&view=1');await expect(page.getByText('Slip gaji Januari 2026 belum tersedia.',{exact:true})).toBeVisible();await expect(page.locator('[data-print]')).toBeDisabled();await expect(page.getByRole('button',{name:'Unduh PDF',exact:true})).toBeDisabled();expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);
  for(const bad of ['bulan=13&tahun=2026','bulan=&tahun=2026','bulan=10&tahun=abc']){const response=await page.goto(base+'/karyawan/slip?'+bad+'&view=1');expect(response.status()).toBe(422);await closeFeedback(page);await expect(page.locator('.employee-filters')).toBeVisible();expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);}
  await page.goto(base+'/karyawan/slip?bulan=10&tahun=2026&nip=DUMMY002&view=1');expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);await expect(page.locator('.employee-print-slip')).toHaveAttribute('data-slip-nip','DUMMY001');
  const fields=['gapok','t_jabatan','t_harian','t_konsumsi','ulembur','uinfal','uovertime','lain_lain','kelebihan','lain1','makan','cash_bon','pinjaman_lunak','absensi','tabungan','arisan','pinjaman_bsm','p_anak','plain','tpenghasilan','tpotongan','tbersih'];
  for(const field of fields)await expect(page.locator(`[data-slip-field="${field}"]`)).toHaveText(['gapok','tpenghasilan','tbersih'].includes(field)?'3.000.000':'0');
  // The attachment endpoint always resolves the authenticated employee, never a browser NIP.
  const attachment=await page.request.get(base+'/karyawan/slip/pdf?bulan=10&tahun=2026&nip=DUMMY002');expect(attachment.status()).toBe(200);expect(attachment.headers()['content-type']).toBe('application/pdf');expect(attachment.headers()['cache-control']).toContain('no-store');expect(attachment.headers()['content-disposition']).toContain('attachment');
  expect((await page.request.get(base+'/karyawan/slip/pdf?bulan=11&tahun=2026&nip=DUMMY002')).status()).toBe(404);
  expect((await page.request.get(base+'/karyawan/slip/pdf?bulan=13&tahun=2026')).status()).toBe(422);
  // Both controls update the visible document immediately without a submit button.
  await choose(page,'slip-month','11');await expect(page.locator('.employee-slip-placeholder')).toContainText('November 2026 belum tersedia.');await expect(page.locator('[data-print]')).toBeDisabled();
  await choose(page,'slip-month','10');await expect(page.locator('.employee-print-document')).toBeVisible();await expect(page.locator('[data-print]')).toBeEnabled();
  const extreme=JSON.parse(fixture('extremes'));fs.writeFileSync(path.join(output,'portrait-expected.json'),JSON.stringify(extreme));await page.goto(base+'/karyawan/slip?bulan=1&tahun=2027&view=1');expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);await expect(page.locator('.employee-print-document')).toBeVisible();await page.pdf({path:path.join(output,'employee-slip-a4-long.pdf'),preferCSSPageSize:true,printBackground:true});await downloadPdf(page,'employee-slip-download-long.pdf');expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(0);
  await choose(page,'slip-year','2026');await expect(page.locator('.employee-slip-placeholder')).toContainText('Januari 2026 belum tersedia.');await choose(page,'slip-year','2027');await expect(page.locator('.employee-print-document')).toContainText(extreme.name);
  // A future history entry must not replace the current calendar default.
  await page.goto(base+'/karyawan/slip');await expect(page.locator('#slip-year')).toHaveValue(String(state.current_year));await expect(page.locator('#slip-month')).toHaveValue(String(state.current_month));await expect(page.locator(`#slip-year option[value="${state.current_year}"]`)).toHaveCount(1);
  for(const width of [1440,390,360]){
   await page.setViewportSize({width,height:1000});await page.goto(base+'/karyawan/slip?bulan=10&tahun=2026');await noOverflow(page);await page.screenshot({path:path.join(output,`preview-${width}.png`),fullPage:true});
   const layout=await page.locator('.employee-filters').evaluate(form=>Array.from(form.children,e=>({top:e.getBoundingClientRect().top,bottom:e.getBoundingClientRect().bottom})));if(width===1440)expect(Math.max(...layout.map(r=>r.bottom))-Math.min(...layout.map(r=>r.bottom))).toBeLessThan(2);
   await page.getByRole('button',{name:'Cetak',exact:true}).click();expect(await page.evaluate(()=>window.employeePrintCalls)).toBe(1);
   if(width===360){await page.getByRole('button',{name:'Buka navigasi'}).click();await expect(page.locator('body')).toHaveClass(/sidebar-open/);await expect(page.locator('.sidebar').getByRole('link',{name:'Ubah Password',exact:true})).toBeVisible();await page.getByRole('button',{name:'Tutup navigasi',exact:true}).click({position:{x:350,y:50}});await expect(page.locator('body')).not.toHaveClass(/sidebar-open/);}
   await page.goto(base+'/karyawan/slip?bulan=1&tahun=2027');await noOverflow(page);await page.screenshot({path:path.join(output,`preview-long-${width}.png`),fullPage:true});
   for(const amount of await page.locator('[data-slip-field]').all())expect(await amount.evaluate(e=>e.scrollWidth<=e.clientWidth+1)).toBe(true);
   await page.goto(base+'/karyawan/slip?bulan=2&tahun=2026');await noOverflow(page);await expect(page.locator('[data-print]')).toBeDisabled();await page.screenshot({path:path.join(output,`empty-${width}.png`),fullPage:true});
   await page.goto(base+'/karyawan/ubah-password');await noOverflow(page);await page.screenshot({path:path.join(output,`password-${width}.png`),fullPage:true});
  }
  console.log('PASS: own-period selection; legacy links; invalid/empty periods do not print; ownership and old-session isolation; long-value portrait fixtures; 1440/390/360 layouts.');
  const admin=await newPage();await login(admin,base,'qa_employee_admin',adminPassword);await expect(admin).toHaveURL(base+'/');await admin.goto(base+'/akun-karyawan');
  await expect(admin.getByRole('heading',{name:'Buat akun karyawan'})).toHaveCount(0);await expect(admin.locator('#account-employee,#reveal-admin-password,#reveal-password-form')).toHaveCount(0);
  const actor=state.accounts.find(a=>a.username==='DUMMY001'),row=admin.locator(`[data-account-id="${actor.id}"]`),modal=admin.locator('#employee-password-modal');
  const closePassword=async()=>{await modal.locator('.modal-footer').getByRole('button',{name:'Tutup',exact:true}).click();await expect(modal).toBeHidden();await expect(admin.locator('.modal-backdrop')).toHaveCount(0);await expect(admin.locator('#reveal-password-value')).toBeEmpty();};
  for(const width of [1440,390,360]){
   await admin.setViewportSize({width,height:1000});await admin.evaluate(()=>scrollTo(0,0));await noOverflow(admin);if(width<760)expect((await admin.locator('.employee-account-heading').boundingBox()).height).toBeLessThan(300);await admin.screenshot({path:path.join(output,`accounts-${width}.png`),fullPage:true});
   await row.getByRole('button',{name:'Lihat Password',exact:true}).click();await expect(admin.locator('#reveal-password-value')).toHaveText('Secret88');await expect(modal.locator('.modal-footer button')).toBeFocused();await noOverflow(admin);await admin.screenshot({path:path.join(output,`password-direct-${width}.png`)});await closePassword();
  }
  const adminToken=await csrf(admin),postReveal=(id=actor.id,csrfValue=adminToken)=>admin.request.post(base+'/akun-karyawan/password',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:csrfValue,id}});
  expect((await postReveal(actor.id,'bad')).status()).toBe(419);expect((await postReveal(1)).status()).toBe(404);expect((await admin.request.get(base+'/akun-karyawan/password')).status()).toBe(404);
  for(let i=0;i<6;i++)expect((await postReveal()).status()).toBe(200);
  fixture('corrupt');expect((await postReveal()).status()).toBe(503);fixture('restore');const revealed=await postReveal();expect(revealed.headers()['cache-control']).toContain('no-store');expect((await revealed.json()).password).toBe('Secret88');
  await require('./employee-onboarding')({admin,base,newPage,fixture,output,start,extraServers,freePort,ready,login,later,changePassword,closeFeedback,noOverflow,csrf});
  const createdAccount=await admin.request.post(base+'/akun-karyawan',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:adminToken,action:'create',employee_id:state.new_employee_id}});expect(createdAccount.ok()).toBe(true);
  const leading=await newPage();await login(leading,base,'00042',initial);await expect(leading).toHaveURL(base+'/karyawan/slip');await later(leading);await expect(leading.locator('.employee-slip-placeholder')).toContainText('belum tersedia.');await expect(leading.locator('[data-print]')).toBeDisabled();
  const leadToken=await csrf(leading);await leading.request.post(base+'/logout',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:leadToken}});await login(leading,base,'00042',initial);await expect(leading.locator('#feedback-title')).toHaveText('Ubah Password Awal');await leading.getByRole('button',{name:'Ubah Sekarang',exact:true}).click();await expect(leading).toHaveURL(base+'/karyawan/ubah-password');await changePassword(leading,initial,'Nol00042');await expect(leading).toHaveURL(base+'/karyawan/slip');await closeFeedback(leading);await expect(leading.locator('#feedback-modal')).toBeHidden();
  const setActive=value=>admin.request.post(base+'/akun-karyawan',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:adminToken,action:'set_active',id:actor.id,is_active:value}});
  expect((await setActive('0')).ok()).toBe(true);await page.goto(base+'/karyawan/slip');await expect(page).toHaveURL(base+'/login');expect((await setActive('1')).ok()).toBe(true);await login(page,base,'DUMMY001','Secret88');await expect(page).toHaveURL(base+'/karyawan/slip');
  await admin.goto(base+'/akun-karyawan');await closeFeedback(admin);await admin.getByLabel('Cari nama atau nomor induk').fill('00042');await admin.getByRole('button',{name:'Cari akun',exact:true}).click();await expect(admin.locator('.employee-account-row')).toHaveCount(1);await expect(admin.locator('.employee-account-row')).toContainText('00042');
  fixture('invalidate_admin');expect((await postReveal()).status()).toBe(401);
  const unauth=await newPage();expect((await unauth.request.get(base+'/karyawan/slip/pdf?bulan=10&tahun=2026',{headers:{'X-Requested-With':'XMLHttpRequest'}})).status()).toBe(401);expect((await unauth.request.post(base+'/akun-karyawan/password',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{_csrf:adminToken,id:actor.id}})).status()).toBe(401);
  const rootPort=await freePort(),rootOrigin=`http://127.0.0.1:${rootPort}`;rootServer=start(rootPort,root);await ready(rootOrigin);const rootPage=await newPage();await login(rootPage,rootOrigin,'DUMMY003',personal[2]);await expect(rootPage).toHaveURL(rootOrigin+'/karyawan/slip');await rootPage.goto(rootOrigin+'/karyawan/slip?bulan=10&tahun=2026&view=1');expect(await rootPage.evaluate(()=>window.employeePrintCalls)).toBe(0);await expect(rootPage.locator('.employee-print-document')).toBeVisible();await rootPage.getByRole('button',{name:'Cetak',exact:true}).click();expect(await rootPage.evaluate(()=>window.employeePrintCalls)).toBe(1);
  const after=JSON.parse(fixture('status'));expect(after.reveal_audit).toBe(11);expect(errors).toEqual([]);
  console.log('PASS: direct password reveal without reauthentication; CSRF/role/session checks; audit; encrypted-data failure; account activation; leading zeroes; reminder after relogin; root/subfolder print; no JS errors.');
 }finally{if(browser)await browser.close();for(const child of [server,rootServer,...extraServers]){if(child&&child.exitCode===null){const stopped=new Promise(r=>child.once('exit',r));child.kill();await stopped;}}if(created)fixture('cleanup');}
})().catch(error=>{console.error(error);process.exitCode=1;});
