// Functional test E2E lewat browser nyata (Chrome headless) terhadap server dev.
// node e2e.mjs <outdir> <pdfPath>
import puppeteer from 'puppeteer-core';

const [outDir = 'tests/e2e/screenshots', pdfPath = 'tests/e2e/fixtures/bukti.pdf'] = process.argv.slice(2);
const BASE = process.env.APP_URL_E2E || 'http://127.0.0.1:8123';
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail ? ' — ' + detail : ''}`); };

const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true, args: ['--no-sandbox'] });

async function session(email) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    page.jsErrors = [];
    page.on('pageerror', (e) => page.jsErrors.push(e.message));
    await page.goto(BASE + '/login', { waitUntil: 'networkidle0' });
    await page.type('#email', email);
    await page.type('#password', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    return page;
}
const text = (page) => page.evaluate(() => document.body.innerText);
const go = (page, path) => page.goto(BASE + path, { waitUntil: 'networkidle0' });
const submitVia = async (page, selector) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click(selector)]);

// ---------- 1. Staff: input Pengeluaran Kas + lampiran, submit ----------
const staff = await session('staff@finance.test');
check('Login staff → dashboard', (await text(staff)).includes('Selamat Datang'));
await go(staff, '/transactions/create/pengeluaran-kas');
await staff.type('#party_name', 'CV Uji E2E');
await staff.type('#amount', '2.750.000');
const previewText = await staff.evaluate(() => [...document.querySelectorAll('p')].map((p) => p.innerText).find((t) => t.startsWith('Rp 2.750.000')) || '');
check('Preview nominal Rupiah real-time', previewText.startsWith('Rp 2.750.000'), previewText);
await staff.type('#description', 'E2E pembelian perlengkapan kantor');
const fileInput = await staff.$('input[type=file][name="attachments[]"]');
await fileInput.uploadFile(pdfPath);
await staff.screenshot({ path: `${outDir}/e2e_1_form.png`, fullPage: true });
await submitVia(staff, 'button[name=action][value=submit]');
let body = await text(staff);
const number = (body.match(/BKK-\d{4}-\d{2}-\d{4}/) || [])[0];
check('Transaksi tersimpan & diajukan (Pending Review)', body.includes('diajukan untuk review') && body.includes('PENDING REVIEW'), number);
check('Lampiran terunggah', body.includes('bukti.pdf'));
await staff.screenshot({ path: `${outDir}/e2e_2_submitted.png`, fullPage: true });

// ---------- 2. Manager: reject tanpa alasan diblok UI, lalu approve ----------
const manager = await session('manager@finance.test');
await go(manager, '/approvals');
const card = await manager.$$eval('a', (as, num) => (as.find((a) => a.innerText.includes(num)) || {}).href, number);
await manager.goto(card, { waitUntil: 'networkidle0' });
const rejectDisabled = await manager.$eval('form[action$="/reject"] button', (b) => b.disabled);
check('Tombol Reject nonaktif sebelum alasan diisi (≥5 karakter)', rejectDisabled === true);
await manager.type('#note', 'abc');
check('Tombol Reject tetap nonaktif untuk alasan 3 karakter', await manager.$eval('form[action$="/reject"] button', (b) => b.disabled));
await manager.click('#note', { clickCount: 3 });
await manager.type('#note', 'Dokumen lengkap, disetujui');
check('Tombol Reject aktif setelah alasan ≥5 karakter', !(await manager.$eval('form[action$="/reject"] button', (b) => b.disabled)));
await manager.screenshot({ path: `${outDir}/e2e_3_review.png`, fullPage: true });
await submitVia(manager, 'form[action$="/approve"] button');
body = await text(manager);
const jv = (body.match(/JV-\d{4}-\d{2}-\d{4}/) || [])[0];
check('Approve → jurnal otomatis terbit', body.includes('disetujui') && !!jv, jv);

// ---------- 3. Akuntan: jurnal manual (indikator selisih) & posting batch ----------
const acc = await session('akuntan@finance.test');
await go(acc, '/journals/create');
check('Tombol simpan jurnal nonaktif saat belum seimbang', await acc.$eval('button[value=post]', (b) => b.disabled));
await acc.type('#description', 'E2E penyesuaian');
const selects = await acc.$$('select[name^="lines"]');
await selects[0].select(await acc.$eval('select[name="lines[0][account_id]"] option:nth-child(3)', (o) => o.value));
await selects[1].select(await acc.$eval('select[name="lines[1][account_id]"] option:nth-child(10)', (o) => o.value));
await acc.type('input[name="lines[0][debit]"]', '1000000');
await acc.type('input[name="lines[1][credit]"]', '999999');
let variance = await acc.evaluate(() => [...document.querySelectorAll('p')].find((p) => p.previousElementSibling?.textContent?.includes('Selisih'))?.innerText);
check('Indikator selisih real-time (Rp 1)', variance === 'Rp 1', variance);
check('Tetap nonaktif bila selisih Rp 1', await acc.$eval('button[value=post]', (b) => b.disabled));
await acc.click('input[name="lines[1][credit]"]', { clickCount: 3 });
await acc.type('input[name="lines[1][credit]"]', '1000000');
variance = await acc.evaluate(() => [...document.querySelectorAll('p')].find((p) => p.previousElementSibling?.textContent?.includes('Selisih'))?.innerText);
check('Selisih Rp 0 & tombol aktif saat seimbang', variance === 'Rp 0' && !(await acc.$eval('button[value=post]', (b) => b.disabled)), variance);
await acc.click('button[type=button][class*=btn-secondary]'); // tambah baris
check('Tambah baris jurnal dinamis', (await acc.$$('select[name^="lines"]')).length === 3);
await acc.screenshot({ path: `${outDir}/e2e_4_journal.png`, fullPage: true });
await submitVia(acc, 'button[value=post]');
body = await text(acc);
check('Jurnal manual tersimpan & terposting', body.includes('diposting ke General Ledger') && body.includes('POSTED'));

await go(acc, '/journals/batch');
await acc.evaluate((ref) => { [...document.querySelectorAll('tr')].find((tr) => tr.innerText.includes(ref))?.querySelector('input[type=checkbox]')?.click(); }, jv);
await submitVia(acc, 'button.btn-primary');
body = await text(acc);
check('Posting batch jurnal otomatis berhasil', body.includes(jv) && body.includes('Berhasil diposting'));
await acc.screenshot({ path: `${outDir}/e2e_5_batch.png`, fullPage: true });

// ---------- 4. Buku besar & trial balance ----------
await go(acc, '/gl/trial-balance');
check('Trial balance seimbang', (await text(acc)).includes('Trial Balance Seimbang'));
await acc.screenshot({ path: `${outDir}/e2e_6_tb.png`, fullPage: true });
await go(acc, '/gl/ledger');
check('Buku besar per akun tampil', (await text(acc)).includes('Running Balance Ledger'));

// ---------- 5. Auditor read-only & audit log ----------
const aud = await session('auditor@finance.test');
await go(aud, '/settings/audit-logs');
body = await text(aud);
check('Auditor melihat audit log transaksi', body.includes('transaction.approved') && body.includes('journal.posted'));
const res = await aud.goto(BASE + '/journals/create');
check('Auditor ditolak membuka Jurnal Manual (403)', res.status() === 403);

// ---------- 6. Mobile layout ----------
await staff.setViewport({ width: 390, height: 844 });
await go(staff, '/dashboard');
await staff.screenshot({ path: `${outDir}/e2e_7_mobile.png` });
const overflow = await staff.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
check('Tidak ada horizontal scroll di lebar 390px (dashboard)', !overflow);
await staff.click('button[aria-label="Buka menu"]');
await new Promise((r) => setTimeout(r, 300));
check('Menu sidebar mobile dapat dibuka', await staff.$eval('aside', (a) => a.getBoundingClientRect().left >= 0));
await staff.screenshot({ path: `${outDir}/e2e_8_mobile_menu.png` });

const jsErrors = [staff, manager, acc, aud].flatMap((p) => p.jsErrors);
check('Tidak ada error JavaScript di browser', jsErrors.length === 0, jsErrors.join(' | '));

await browser.close();
const failed = results.filter((r) => !r.ok).length;
console.log(`\nTOTAL ${results.length} cek, ${results.length - failed} lulus, ${failed} gagal`);
process.exit(failed ? 1 : 0);
