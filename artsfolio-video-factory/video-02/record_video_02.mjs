import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { chromium } from 'playwright';

const videoRoot = path.dirname(new URL(import.meta.url).pathname);
const factoryRoot = path.resolve(videoRoot, '..');
const artsfolioRoot = path.resolve(factoryRoot, '..');
const workRoot = path.join(factoryRoot, 'work', 'video-02');
const rawRoot = path.join(workRoot, 'raw');
const scenes = JSON.parse(fs.readFileSync(path.join(videoRoot, 'scenes.json'), 'utf8'));
const durations = JSON.parse(fs.readFileSync(path.join(workRoot, 'cue-durations.json'), 'utf8'));
const cueStartsPath = path.join(workRoot, 'cue-starts.json');

const logoPath = [
  path.join(artsfolioRoot, 'public', 'assets', 'logo_2.png'),
  path.join(artsfolioRoot, 'public', 'assets', 'logo.png'),
].find(candidate => fs.existsSync(candidate));
if (!logoPath) throw new Error('ArtsFolio logo was not found.');
const logoData = `data:image/png;base64,${fs.readFileSync(logoPath).toString('base64')}`;

const baseUrl = process.env.AF_VIDEO_BASE_URL || 'https://training.artsfol.io';
const email = process.env.AF_VIDEO_EMAIL || '';
const password = process.env.AF_VIDEO_PASSWORD || '';
const headless = (process.env.AF_VIDEO_HEADLESS || 'true') !== 'false';

const browser = await chromium.launch({ headless, args: ['--disable-notifications','--disable-infobars','--hide-scrollbars'] });
const context = await browser.newContext({
  viewport: { width: 1920, height: 1080 },
  deviceScaleFactor: 1,
  recordVideo: { dir: rawRoot, size: { width: 1920, height: 1080 } },
});
const page = await context.newPage();
const video = page.video();
const cueStarts = {};
const startedAt = performance.now();

fs.mkdirSync(rawRoot, { recursive: true });

const elapsed = () => (performance.now() - startedAt) / 1000;

async function desktop() {
  const size = page.viewportSize();
  if (!size || size.width !== 1920 || size.height !== 1080) {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(250);
  }
}
async function healthy(action) {
  const title = await page.title().catch(() => '');
  const body = await page.locator('body').innerText().catch(() => '');
  if (/\b500\b/.test(title) || /internal server error/i.test(body.slice(0, 1000))) {
    throw new Error(`[500 GUARD] action=${action}; url=${page.url()}; title=${title}`);
  }
}
async function goto(route, action = route) {
  const response = await page.goto(new URL(route, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  if (response && response.status() >= 500) throw new Error(`[HTTP ${response.status()}] action=${action}; url=${response.url()}`);
  await page.waitForTimeout(650);
  await healthy(action);
}
async function login() {
  await goto('/login', 'login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await Promise.all([
    page.waitForURL(url => url.pathname.startsWith('/admin'), { timeout: 30000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  await page.waitForTimeout(650);
  await healthy('login-submit');
}
async function scrollTo(selector) {
  const selectors = selector
    .split(',')
    .map(item => item.trim())
    .filter(Boolean);

  for (const candidate of selectors) {
    const visibleTarget = page.locator(`${candidate}:visible`).first();

    if (!(await visibleTarget.count())) {
      continue;
    }

    try {
      await visibleTarget.scrollIntoViewIfNeeded({ timeout: 2500 });
      await page.waitForTimeout(250);
      return true;
    } catch (error) {
      console.warn(
        `[VIDEO 02] Could not scroll to visible target ${candidate}: ` +
        `${error.message}`
      );
    }
  }

  console.warn(
    `[VIDEO 02] No visible scroll target found for selector: ${selector}`
  );
  return false;
}
async function titleCard() {
  await page.setContent(`<!doctype html><html><head><meta charset="utf-8"><style>
html,body{margin:0;width:100%;height:100%;background:#fff;overflow:hidden}
body{display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111;text-align:center}
#brand{opacity:0;transform:translateY(14px);transition:opacity 1.4s ease,transform 1.4s ease}
#title{opacity:0;transform:translateY(18px);transition:opacity 1.2s ease,transform 1.2s ease;margin-top:44px}
</style></head><body><div style="max-width:1350px;padding:60px">
<div id="brand"><img src="${logoData}" style="display:block;max-width:900px;max-height:390px;margin:0 auto 28px;object-fit:contain"><div style="font-size:50px;letter-spacing:.08em;font-weight:700">artsfol.io</div></div>
<div id="title"><div style="font-size:28px;letter-spacing:.15em;text-transform:uppercase;font-weight:700">Training Video 02</div><div style="font-size:60px;line-height:1.08;font-weight:800;margin-top:16px">Site Identity, Branding, and Content</div></div>
</div></body></html>`);
  await page.waitForTimeout(250);
  await page.evaluate(() => { const e=document.getElementById('brand');e.style.opacity='1';e.style.transform='translateY(0)'; });
  await page.waitForTimeout(2100);
  await page.evaluate(() => { const e=document.getElementById('title');e.style.opacity='1';e.style.transform='translateY(0)'; });
  await page.waitForTimeout(4650);
}
async function endCard() {
  await page.setContent(`<!doctype html><html><head><meta charset="utf-8"><style>
html,body{margin:0;width:100%;height:100%;background:#fff;overflow:hidden}
body{display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111;text-align:center}
#brand{opacity:0;transition:opacity 1.2s ease}
</style></head><body><div id="brand"><img src="${logoData}" style="display:block;max-width:900px;max-height:410px;margin:0 auto 28px;object-fit:contain"><div style="font-size:50px;letter-spacing:.08em;font-weight:700">artsfol.io</div></div></body></html>`);
  await page.waitForTimeout(250);
  await page.evaluate(() => document.getElementById('brand').style.opacity='1');
  await page.waitForTimeout(5750);
}
async function perform(action) {
  if (action !== 'public_mobile') await desktop();
  switch (action) {
    case 'settings_identity': await goto('/admin/settings?section=identity', action); break;
    case 'show_identity_top': await goto('/admin/settings?section=identity', action); await scrollTo('input[name="site_title"]'); break;
    case 'show_home_intro': await goto('/admin/settings?section=identity', action); await scrollTo('textarea[name="home_intro"]'); break;
    case 'show_navigation_labels': await goto('/admin/settings?section=identity', action); await scrollTo('input[name="home_tab"]'); break;
    case 'show_page_visibility': await goto('/admin/settings?section=identity', action); await scrollTo('input[name="suppress_about_page"]'); break;
    case 'settings_typography': await goto('/admin/settings?section=typography', action); break;
    case 'show_typography_families': await goto('/admin/settings?section=typography', action); await scrollTo('select[name="font_family_body"]'); break;
    case 'show_typography_sizes': await goto('/admin/settings?section=typography', action); await scrollTo('[data-font-size-control="font_size_body"]'); break;
    case 'settings_colors': await goto('/admin/settings?section=colors-backgrounds', action); break;
    case 'show_core_colors': await goto('/admin/settings?section=colors-backgrounds', action); await scrollTo('input[name="primary_color"]'); break;
    case 'show_surface_colors': await goto('/admin/settings?section=colors-backgrounds', action); await scrollTo('input[name="content_background_color"]'); break;
    case 'settings_branding':
      await goto('/admin/artworks?type=site_images', action);
      break;
    case 'show_logo_controls':
      await goto('/admin/artworks?type=site_images', action);
      await scrollTo('.tenant-admin-main');
      break;
    case 'show_background_controls':
      await goto('/admin/settings?section=colors-backgrounds', action);
      await scrollTo(
        'input[name="background_color"], select[name="background_mode"]'
      );
      break;
    case 'show_watermark_controls':
      await goto('/admin/settings?section=watermark', action);
      await scrollTo('input[name="watermark_enabled"]');
      break;
    case 'content_page': await goto('/admin/content', action); break;
    case 'show_content_home': await goto('/admin/content', action); await scrollTo('textarea[name="home_intro"]'); break;
    case 'show_content_about': await goto('/admin/content', action); await scrollTo('textarea[name="about_content"]'); break;
    case 'show_content_contact': await goto('/admin/content', action); await scrollTo('textarea[name="contact_details"]'); break;
    case 'show_social_links': await goto('/admin/content', action); await scrollTo('input[name="instagram_url"]'); break;
    case 'show_content_save': await goto('/admin/content', action); await scrollTo('button[type="submit"]'); break;
    case 'site_images_page': await goto('/admin/artworks?type=site_images', action); break;
    case 'artworks_page': await goto('/admin/artworks', action); break;
    case 'public_home': await goto('/', action); break;
    case 'public_about': await goto('/about', action); break;
    case 'public_contact': await goto('/contact', action); break;
    case 'public_mobile': await goto('/', action); await page.setViewportSize({width:900,height:1080}); await page.waitForTimeout(250); break;
    default: throw new Error(`Unknown action: ${action}`);
  }
}

try {
  await titleCard();
  await login();
  let ordinal=0;
  for (const scene of scenes) {
    for (const cue of scene.cues) {
      ordinal += 1;
      const key=`${String(ordinal).padStart(3,'0')}-${scene.id}-${cue.id}`;
      await perform(cue.action);
      cueStarts[key]=elapsed();
      await page.waitForTimeout(Math.max(1200, Math.round(Number(durations[key])*1000)));
    }
  }
  await endCard();
  fs.writeFileSync(cueStartsPath, JSON.stringify(cueStarts,null,2)+'\n');
} catch (error) {
  fs.writeFileSync(path.join(workRoot,'video02-recording-error.txt'),`${error.stack||error}\n`);
  throw error;
} finally {
  await context.close();
  await browser.close();
}
fs.copyFileSync(await video.path(), path.join(rawRoot,'video02-browser.webm'));
console.log('[PASS] Video 02 browser recording complete.');
