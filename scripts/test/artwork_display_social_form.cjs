/* Isolated browser tests; uses the existing Playwright installation. */
const { chromium } = require('playwright');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        const css = ['site.css', 'platform.css', 'tenant-admin.css', 'admin-shell-refactor.css', 'artwork-display.css']
            .map(file => fs.readFileSync(path.join(root, 'public/assets', file), 'utf8')).join('\n');
        for (const viewport of [{width:390,height:844},{width:1440,height:1000}]) {
            await page.setViewportSize(viewport);
            for (const [width,height] of [[1200,300],[300,1200],[640,640]]) {
                const src='data:image/svg+xml,'+encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><rect width="100%" height="100%" fill="red"/></svg>`);
                for (const parentClass of ['artwork-main-display','artwork-edit-preview']) {
                    await page.setContent(`<style>${css}</style><figure class="${parentClass}"><img class="artwork-main-image" src="${src}"></figure>`);
                    await page.locator('img').evaluate(img=>img.decode());
                    const geometry=await page.locator('img').evaluate(img=>({width:img.getBoundingClientRect().width,height:img.getBoundingClientRect().height,fit:getComputedStyle(img).objectFit,radius:getComputedStyle(img).borderRadius,right:img.getBoundingClientRect().right}));
                    assert(Math.abs(geometry.width/geometry.height-width/height)<0.01,JSON.stringify(geometry));
                    assert(geometry.right<=viewport.width && geometry.height<=viewport.height*.8+1);
                    assert.equal(geometry.fit,'contain'); assert.equal(geometry.radius,'0px');
                }
            }
        }
        await page.setContent(`<style>${css}</style><div class="directory-thumbnail-preview"><img></div>`);
        assert.equal(await page.locator('img').evaluate(img=>getComputedStyle(img).objectFit),'cover');
        const html=execFileSync('php',[path.join(__dirname,'social_post_requests.php'),'--html'],{encoding:'utf8'});
        const script=fs.readFileSync(path.join(root,'public/assets/social-publishing.js'),'utf8');
        await page.route('**/*',route=>route.fulfill({contentType:'application/json',body:'{}'}));
        await page.goto('http://artsfolio.test/admin/social/compose');
        await page.setContent(html.replace(/<script[^>]*>[\s\S]*?<\/script>/g,''));
        // The publishing mode is serialized even without JS or a submitter (FormData/form.submit).
        assert.equal(await page.locator('form').evaluate(form=>new FormData(form).get('publish_action')),'post_now');
        await page.addScriptTag({content:script});
        await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
        await page.waitForFunction(()=>document.querySelector('[data-social-submit]').textContent==='Post Now');
        assert.equal(await page.locator('[name=scheduled_local]').isDisabled(),true);
        await page.locator('details').evaluate(details=>details.open=true);
        await page.selectOption('[data-social-crop]','square');
        assert.equal(await page.locator('[data-social-media-card] img').evaluate(img=>getComputedStyle(img).objectFit),'cover');
        await page.selectOption('[data-social-crop]','original');
        assert.equal(await page.locator('[data-social-media-card] img').evaluate(img=>getComputedStyle(img).objectFit),'contain');
        assert.equal(await page.locator('form').evaluate(form=>new FormData(form).has('scheduled_local')),false);
        await page.selectOption('[name=publish_action]','schedule');
        assert.equal(await page.locator('[name=scheduled_local]').isDisabled(),false);
        assert.equal(await page.locator('form').evaluate(form=>form.checkValidity()),false);
        await page.fill('[name=scheduled_local]','2099-07-01T12:00');
        assert.equal(await page.locator('form').evaluate(form=>form.checkValidity()),true);
        assert.equal(await page.locator('form').evaluate(form=>new FormData(form).get('publish_action')),'schedule');
        await page.selectOption('[name=publish_action]','post_now');
        assert.equal(await page.locator('form').evaluate(form=>new FormData(form).get('publish_action')),'post_now');
        console.log('Browser checks passed: 12 artwork geometry cases; immediate/scheduled controls and submitter-independent serialization.');
    } finally { await browser.close(); }
})().catch(error=>{ console.error(error);process.exitCode=1; });
