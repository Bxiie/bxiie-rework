/* Run with Node + Playwright installed; tests use only isolated rendered fixtures. */
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const html = Object.fromEntries(['1', '2', 'platform'].map(scope => [scope,
  execFileSync('php', [path.join(__dirname, 'fixtures/admin_sidebar_page.php'), scope], { encoding: 'utf8' })]));

(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.pathname.startsWith('/assets/')) {
        const file = path.join(root, 'public', url.pathname);
        if (fs.existsSync(file)) return route.fulfill({ path: file });
        return route.fulfill({ status: 204, body: '' });
      }
      const scope = url.pathname.startsWith('/platform') ? 'platform' : url.pathname.includes('tenant2') ? '2' : '1';
      return route.fulfill({ contentType: 'text/html', body: html[scope] });
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    for (const scope of ['tenant', 'platform']) {
      const url = scope === 'tenant' ? '/admin/artworks' : '/platform/admin';
      await page.goto('http://artsfolio.test' + url);
      const button = page.locator('.admin-sidebar-toggle');
      const sidebar = page.locator(`#${scope}-admin-sidebar`);
      const main = page.locator(`.${scope}-admin-main`);
      await button.waitFor({ state: 'visible' });
      assert.equal(await button.getAttribute('aria-expanded'), 'true');
      const before = (await main.boundingBox()).width;
      await button.click();
      assert.equal(await sidebar.isVisible(), false);
      assert.equal(await button.innerText(), '☰\nShow sidebar');
      assert((await main.boundingBox()).width >= before + 250, 'Collapsing must reclaim sidebar width: ' + before + ' -> ' + (await main.boundingBox()).width + ' shell ' + await page.locator('[data-admin-sidebar-shell]').evaluate(el => JSON.stringify({display:getComputedStyle(el).display,columns:getComputedStyle(el).gridTemplateColumns,width:getComputedStyle(el).width})));
      await page.reload();
      await button.waitFor({ state: 'visible' });
      assert.equal(await sidebar.isVisible(), false, 'Preference survives page load');
      await page.setViewportSize({ width: 600, height: 900 });
      await page.waitForFunction(() => document.querySelector('[data-sidebar-toggle-label]').textContent === 'Open admin menu');
      await button.click();
      assert.equal(await sidebar.isVisible(), true);
      await sidebar.locator('a').first().focus();
      await page.keyboard.press('Escape');
      assert.equal(await sidebar.isVisible(), false);
      assert.equal(await button.evaluate(el => el === document.activeElement), true, 'Escape restores focus');
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.waitForFunction(() => document.querySelector('[data-sidebar-toggle-label]').textContent === 'Show sidebar');
      assert.equal(await sidebar.isVisible(), false, 'Mobile actions preserve desktop preference');
      await button.focus();
      await page.keyboard.press('Enter');
      assert.equal(await sidebar.isVisible(), true, 'Keyboard reopens sidebar');
      await page.reload();
      await button.waitFor({ state: 'visible' });
      assert.equal(await sidebar.isVisible(), true, 'Expanded preference persists');
      console.log(scope + ': width, persistence, mobile resize, keyboard and focus checks passed');
    }
    await page.goto('http://artsfolio.test/admin/artworks');
    await page.locator('.admin-sidebar-toggle').click();
    await page.goto('http://artsfolio.test/tenant2/admin/artworks');
    assert.equal(await page.locator('#tenant-admin-sidebar').isVisible(), true, 'Tenant preferences are independent');
    await page.screenshot({ path: '/tmp/artsfolio-sidebar-expanded.png', fullPage: true });
    await page.locator('.admin-sidebar-toggle').click();
    await page.screenshot({ path: '/tmp/artsfolio-sidebar-collapsed.png', fullPage: true });
    await page.addInitScript(() => Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage disabled'); } }));
    await page.reload();
    await page.locator('.admin-sidebar-toggle').click();
    assert.equal(await page.locator('#tenant-admin-sidebar').isVisible(), false, 'Storage denial must not break toggling');
    assert.deepEqual(errors, [], 'No browser JavaScript errors');
    await context.close();
    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 600, height: 900 } });
    const fallback = await noJs.newPage();
    await fallback.route('**/*', route => {
      const pathname = new URL(route.request().url()).pathname;
      const file = path.join(root, 'public', pathname);
      return pathname.startsWith('/assets/') && fs.existsSync(file)
        ? route.fulfill({ path: file }) : route.fulfill({ contentType: 'text/html', body: html['1'] });
    });
    await fallback.goto('http://artsfolio.test/admin');
    assert.equal(await fallback.locator('#tenant-admin-sidebar').isVisible(), true, 'Navigation works without JavaScript');
    await noJs.close();
    console.log('Sidebar isolation, unavailable storage, and no-JavaScript fallback checks passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
