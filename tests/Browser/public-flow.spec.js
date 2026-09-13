import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('guest access and logout remain protected on desktop and mobile', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/login');
    await expect(page.locator('input[type="password"]').first()).toBeVisible();
    const privateResponse = await page.request.get('/api/attendance/today', { headers: { Accept: 'application/json' } });
    expect(privateResponse.status()).toBe(401);
    expect(privateResponse.headers()['cache-control']).toContain('no-store');
    await page.goto('/logout');
    const members = await page.request.get('/?ajax=get_members', { headers: { Accept: 'application/json' } });
    expect(members.status()).toBe(401);
    expect(errors).toEqual([]);
});

test('public login accessibility and timing baseline', async ({ page }, testInfo) => {
    await page.goto('/login');
    const accessibility = await new AxeBuilder({ page }).analyze();
    console.log('AXE_BASELINE', testInfo.project.name, JSON.stringify(accessibility.violations.map(({id,impact,nodes}) => ({id,impact,count:nodes.length}))));
    expect(accessibility.violations).toEqual([]);
    await testInfo.attach('accessibility-baseline.json', { body: JSON.stringify(accessibility.violations, null, 2), contentType: 'application/json' });
    const timing = await page.evaluate(() => performance.getEntriesByType('navigation')[0].toJSON());
    await testInfo.attach('navigation-baseline.json', { body: JSON.stringify(timing, null, 2), contentType: 'application/json' });
    await expect(page.locator('body')).toBeVisible();
});
