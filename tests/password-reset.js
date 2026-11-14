'use strict';
const { chromium, expect } = require('@playwright/test');
const { spawn, execFileSync } = require('node:child_process');
const { randomBytes, createHash } = require('node:crypto');
const net = require('node:net');
const path = require('node:path');
const fs = require('node:fs');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_PATH || 'php';
const database = 'penggajian_auth_test_' + randomBytes(4).toString('hex');
const fixture = command => execFileSync(php, ['tests/auth-fixtures.php', database, command], { cwd: root, encoding: 'utf8' });
const messages = [];
let rejectNext = false;
const smtp = net.createServer(socket => {
  socket.setEncoding('utf8');
  socket.write('220 localhost SMTP test\r\n');
  let buffer = '', data = false, body = '';
  socket.on('data', chunk => {
    buffer += chunk;
    while (buffer.includes('\r\n')) {
      const end = buffer.indexOf('\r\n');
      const line = buffer.slice(0, end); buffer = buffer.slice(end + 2);
      if (data) {
        if (line === '.') { messages.push(body); body = ''; data = false; socket.write('250 Accepted\r\n'); }
        else body += line + '\r\n';
      } else if (/^EHLO|^HELO/.test(line)) socket.write('250-localhost\r\n250 SIZE 100000\r\n');
      else if (/^MAIL FROM:|^RCPT TO:|^RSET/.test(line)) socket.write('250 OK\r\n');
      else if (line === 'DATA') {
        if (rejectNext) { rejectNext = false; socket.write('550 Delivery refused\r\n'); }
        else { data = true; socket.write('354 Send data\r\n'); }
      } else if (line === 'QUIT') socket.end('221 Bye\r\n');
      else socket.write('500 Unsupported command\r\n');
    }
  });
  socket.on('error', () => {});
});
async function freePort() {
  const server = net.createServer();
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;
  await new Promise(resolve => server.close(resolve));
  return port;
}
async function closeFeedback(page) {
  const modal = page.locator('#feedback-modal');
  if (await modal.isVisible()) { await page.locator('#feedback-accept').click(); await expect(modal).toBeHidden(); }
}
function linkFromMessage() {
  const link = messages.at(-1)?.match(/https?:\/\/[^\s]+\/reset-password\?token=[a-f0-9]{64}/)?.[0];
  expect(link).toBeTruthy(); return link;
}
async function requestReset(page, base, email = 'qa-auth@example.com') {
  await page.goto(base + '/forgot-password');
  await closeFeedback(page);
  await page.getByLabel('Email pemulihan', { exact: true }).fill(email);
  await page.getByRole('button', { name: 'Kirim tautan pengaturan ulang' }).click();
  await expect(page.getByRole('status')).toContainText('Jika email terdaftar');
  await closeFeedback(page);
}
async function login(page, base, password) {
  await page.goto(base + '/login'); await closeFeedback(page);
  await page.getByLabel('ID pengguna', { exact: true }).fill('qa_auth');
  await page.getByLabel('Kata sandi', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click();
}
async function submitPassword(page, password, confirmation = password) {
  await page.getByLabel('Kata sandi baru', { exact: true }).fill(password);
  await page.getByLabel('Konfirmasi kata sandi', { exact: true }).fill(confirmation);
  await page.getByRole('button', { name: 'Simpan kata sandi baru' }).click();
}
(async () => {
  let browser, server, created = false;
  try {
    fixture('create'); created = true;
    await new Promise(resolve => smtp.listen(0, '127.0.0.1', resolve));
    const port = await freePort();
    const base = `http://127.0.0.1:${port}/${path.basename(root)}`;
    server = spawn(php, ['-d', 'variables_order=EGPCS', '-S', `127.0.0.1:${port}`, '-t', path.dirname(root), path.join(root, 'scripts/router.php')], {
      cwd: root, env: { ...process.env, DB_DATABASE: database, APP_URL: base,
        SMTP_HOST: '127.0.0.1', SMTP_PORT: String(smtp.address().port), SMTP_AUTH: 'false',
        SMTP_ENCRYPTION: 'none', SMTP_USERNAME: '', SMTP_PASSWORD: '', MAIL_FROM_ADDRESS: 'qa-sender@example.com' }, stdio: 'ignore'
    });
    for (let i = 0; i < 80; i++) {
      try { if ((await fetch(base + '/setup')).ok) break; } catch {}
      if (i === 79) throw new Error('Server PHP uji tidak siap.');
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const oldContext = await browser.newContext();
    const oldPage = await oldContext.newPage();
    await oldPage.goto(base + '/setup');
    await oldPage.getByLabel('Nama lengkap', { exact: true }).fill('QA Pemulihan');
    await oldPage.getByLabel('Email pemulihan', { exact: true }).fill('qa-auth@example.com');
    await oldPage.getByLabel('ID pengguna', { exact: true }).fill('qa_auth');
    await oldPage.getByLabel('Kata sandi', { exact: true }).fill('Qa!Auth-Before-2026');
    await oldPage.getByLabel('Konfirmasi kata sandi', { exact: true }).fill('Qa!Auth-Before-2026');
    await oldPage.getByRole('button', { name: 'Buat akun pengelola' }).click();
    await expect(oldPage).toHaveURL(base + '/login'); await closeFeedback(oldPage);
    await login(oldPage, base, 'Qa!Auth-Before-2026'); await expect(oldPage).toHaveURL(base + '/');
    const page = await browser.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/login');
    await page.getByRole('link', { name: 'Lupa kata sandi?' }).click();
    await expect(page).toHaveURL(base + '/forgot-password');
    await page.getByRole('link', { name: 'Kembali ke halaman masuk' }).click();
    await expect(page).toHaveURL(base + '/login');
    await page.goto(base + '/forgot-password');
    const csrfResponse = await page.request.post(base + '/forgot-password', { form: { email: 'qa-auth@example.com' } });
    expect(csrfResponse.status()).toBe(419);
    await requestReset(page, base, 'unknown@example.com'); expect(messages).toHaveLength(0);
    await requestReset(page, base); expect(messages).toHaveLength(1);
    const firstLink = linkFromMessage();
    const token = new URL(firstLink).searchParams.get('token');
    const status = JSON.parse(fixture('status'));
    expect(status.tokens[0].token_hash).toBe(createHash('sha256').update(token).digest('hex'));
    expect(status.tokens[0].token_hash).not.toBe(token);
    expect(messages[0]).toContain('To: QA Pemulihan <qa-auth@example.com>');
    await requestReset(page, base); expect(messages).toHaveLength(2);
    const secondLink = linkFromMessage();
    await page.goto(firstLink); await closeFeedback(page);
    await expect(page.getByRole('button', { name: 'Simpan kata sandi baru' })).toHaveCount(0);
    await page.getByRole('link', { name: 'Minta tautan baru' }).click();
    await expect(page).toHaveURL(base + '/forgot-password');
    rejectNext = true; fixture('limits');
    await requestReset(page, base); expect(messages).toHaveLength(2);
    await page.goto(secondLink); await expect(page.getByLabel('Kata sandi baru', { exact: true })).toBeVisible();
    await submitPassword(page, 'Qa!Auth-After-2026', 'Qa!Auth-Mismatch-2026');
    await expect(page.locator('#feedback-message')).toContainText('Konfirmasi'); await closeFeedback(page);
    const csrf = await page.locator('[name=_csrf]').inputValue();
    const invalid = await page.request.post(base + '/reset-password', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, form: { _csrf: csrf, token: new URL(secondLink).searchParams.get('token'), password: 'short', password_confirmation: 'short' }
    });
    expect(invalid.status()).toBe(422);
    await page.getByRole('button', { name: 'Tampilkan kata sandi', exact: true }).click();
    await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Sembunyikan kata sandi', exact: true }).click();
    await submitPassword(page, 'Qa!Auth-After-2026');
    await expect(page).toHaveURL(base + '/login'); await closeFeedback(page);
    const after = JSON.parse(fixture('status'));
    expect(after.tokens).toHaveLength(0); expect(after.user.session_version).toBe(2); expect(after.audit).toBe(1);
    await oldPage.goto(base + '/profil'); await expect(oldPage).toHaveURL(base + '/login');
    await page.goto(secondLink); await closeFeedback(page);
    await expect(page.getByRole('alert')).toContainText('Tautan tidak valid');
    await login(page, base, 'Qa!Auth-Before-2026');
    await expect(page.locator('#feedback-message')).toContainText('tidak sesuai'); await closeFeedback(page);
    await login(page, base, 'Qa!Auth-After-2026'); await expect(page).toHaveURL(base + '/');
    await page.goto(base + '/profil'); await expect(page.getByLabel('Email pemulihan')).toHaveValue('qa-auth@example.com');
    await page.getByLabel('Kata sandi saat ini').fill('Qa!Auth-After-2026');
    await page.getByLabel('Email pemulihan').fill('updated@example.com');
    await page.getByRole('button', { name: 'Simpan perubahan' }).click();
    await expect(page.locator('#feedback-message')).toContainText('Profil berhasil'); await closeFeedback(page);
    fixture('limits'); await requestReset(page, base, 'updated@example.com');
    const expiringLink = linkFromMessage(); fixture('expire');
    await page.goto(expiringLink); await closeFeedback(page);
    await expect(page.getByRole('alert')).toContainText('kedaluwarsa');
    fixture('limits');
    const beforeLimit = messages.length;
    for (let i = 0; i < 6; i++) await requestReset(page, base, 'updated@example.com');
    expect(messages.length - beforeLimit).toBe(3);
    const qaDir = path.join(root, 'storage/qa/auth'); fs.mkdirSync(qaDir, { recursive: true });
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [name, route] of [['login','/login'],['forgot','/forgot-password'],['reset',new URL(linkFromMessage()).pathname.replace('/' + path.basename(root), '') + new URL(linkFromMessage()).search]]) {
        await page.goto(base + route); await closeFeedback(page);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
        await page.screenshot({ path: path.join(qaDir, `${name}-${width}.png`), fullPage: true });
      }
    }
    expect(errors).toEqual([]);
    console.log('PASS: upgrade idempotent; first-account registration with email; SMTP MIME delivery and failure rollback; CSRF; unknown email; hashed tokens; replacement; confirmation; password validation; reset; old password/session rejection; single use; profile email; expiry; throttling; subfolder links; desktop/mobile layouts; no JS errors.');
  } finally {
    await browser?.close();
    if (server) { server.kill(); await new Promise(resolve => server.once('exit', resolve)); }
    await new Promise(resolve => smtp.close(resolve));
    if (created) fixture('cleanup');
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
