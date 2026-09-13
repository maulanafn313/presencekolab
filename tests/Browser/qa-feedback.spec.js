import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test('QA upload displays server errors and only confirms explicit success', async ({ page }) => {
    const html = execFileSync('php', ['tests/Fixtures/legacy_page.php','/dashboard','admin'], {encoding:'utf8',maxBuffer:8*1024*1024});
    let success = false;
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/qa-test-fixture') return route.fulfill({contentType:'text/html',body:html});
        if (url.searchParams.get('ajax') === 'generate_face_embedding') return route.fulfill({
            status:success ? 200 : 503, contentType:'application/json',
            body:JSON.stringify(success ? {ok:true,face_status:'ready',message:'Berhasil'} : {ok:false,message:'Layanan wajah belum tersedia'})
        });
        if (url.searchParams.has('ajax')) return route.fulfill({contentType:'application/json',body:'{"ok":true,"data":[]}'});
        return route.continue();
    });
    await page.goto('/qa-test-fixture');
    await page.evaluate(() => document.querySelector('#page-qa-panel').classList.remove('hidden'));
    const upload = page.locator('#qa-face-upload');
    const file = {name:'face.png',mimeType:'image/png',buffer:Buffer.from('test-fixture')};
    await upload.setInputFiles(file);
    await expect(page.locator('#qa-face-status')).toContainText('Gagal');
    await expect(page.locator('#qa-face-status')).not.toContainText('berhasil disimpan');
    success = true;
    await upload.setInputFiles([]);
    await upload.setInputFiles(file);
    await expect(page.locator('#qa-face-status')).toContainText('berhasil disimpan');
});
