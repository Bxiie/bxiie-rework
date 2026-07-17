import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { chromium } from 'playwright';

const videoRoot = path.dirname(new URL(import.meta.url).pathname);
const factoryRoot = path.resolve(videoRoot, '..');
const artsfolioRoot = path.resolve(factoryRoot, '..');
const workRoot = path.join(factoryRoot, 'work', 'video-01');
const rawRoot = path.join(workRoot, 'raw');
const scenes = JSON.parse(fs.readFileSync(path.join(videoRoot, 'scenes.json'), 'utf8'));
const durations = JSON.parse(fs.readFileSync(path.join(workRoot, 'cue-durations.json'), 'utf8'));

const logoCandidates = [
  path.join(artsfolioRoot, 'public', 'assets', 'logo_2.png'),
  path.join(artsfolioRoot, 'public', 'assets', 'logo.png'),
];
const logoPath = logoCandidates.find(candidate => fs.existsSync(candidate));
if (!logoPath) throw new Error('ArtsFolio logo was not found.');
const logoData = `data:image/png;base64,${fs.readFileSync(logoPath).toString('base64')}`;

const baseUrl = process.env.AF_VIDEO_BASE_URL || 'https://training.artsfol.io';
const email = process.env.AF_VIDEO_EMAIL || '';
const password = process.env.AF_VIDEO_PASSWORD || '';
const headless = (process.env.AF_VIDEO_HEADLESS || 'true') !== 'false';

fs.mkdirSync(rawRoot, { recursive: true });

const browser = await chromium.launch({
  headless,
  args: ['--disable-notifications', '--disable-infobars', '--hide-scrollbars'],
});
const context = await browser.newContext({
  viewport: { width: 1920, height: 1080 },
  deviceScaleFactor: 1,
  recordVideo: { dir: rawRoot, size: { width: 1920, height: 1080 } },
});
const page = await context.newPage();
const video = page.video();

async function ready() {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(650);
}

async function goto(route) {
  await page.goto(new URL(route, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  await ready();
}

async function login() {
  await goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await Promise.all([
    page.waitForURL(url => url.pathname.startsWith('/admin'), { timeout: 30000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  await ready();
}

async function clearHighlights() {
  await page.locator('[data-af-highlighted="1"]').evaluateAll(elements => {
    for (const el of elements) {
      el.style.outline = el.dataset.afOldOutline || '';
      el.style.outlineOffset = el.dataset.afOldOffset || '';
      el.style.boxShadow = el.dataset.afOldShadow || '';
      delete el.dataset.afHighlighted;
      delete el.dataset.afOldOutline;
      delete el.dataset.afOldOffset;
      delete el.dataset.afOldShadow;
    }
  });
}

async function highlight(selector) {
  const locator = page.locator(selector).first();
  if (!(await locator.count())) return;
  await locator.scrollIntoViewIfNeeded();
  await locator.evaluate(el => {
    el.dataset.afHighlighted = '1';
    el.dataset.afOldOutline = el.style.outline || '';
    el.dataset.afOldOffset = el.style.outlineOffset || '';
    el.dataset.afOldShadow = el.style.boxShadow || '';
    el.style.outline = '7px solid #f3bb45';
    el.style.outlineOffset = '4px';
    el.style.boxShadow = '0 0 0 8px rgba(243,187,69,.2)';
  });
}

async function openingCard() {
  await goto('/');
  await page.evaluate(({ logoData }) => {
    const card = document.createElement('div');
    card.id = 'af-opening-card';
    card.style.cssText = [
      'position:fixed','inset:0','z-index:2147483647',
      'display:flex','align-items:center','justify-content:center',
      'background:#fff','font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
      'color:#111','text-align:center','overflow:hidden'
    ].join(';');
    card.innerHTML = `
      <div style="max-width:1300px;padding:70px">
        <div id="af-brand" style="opacity:0;transform:translateY(14px);transition:opacity 1.4s ease,transform 1.4s ease">
          <img src="${logoData}" alt="ArtsFolio" style="display:block;max-width:980px;max-height:430px;width:auto;height:auto;margin:0 auto 34px;object-fit:contain">
          <div style="font-size:54px;letter-spacing:.08em;font-weight:700">artsfol.io</div>
        </div>
        <div id="af-title" style="opacity:0;transform:translateY(18px);transition:opacity 1.2s ease,transform 1.2s ease;margin-top:54px">
          <div style="font-size:30px;letter-spacing:.15em;text-transform:uppercase;font-weight:700">Training Video 01</div>
          <div style="font-size:64px;line-height:1.08;font-weight:800;margin-top:18px">Your Admin Orientation</div>
        </div>
      </div>`;
    document.body.appendChild(card);
  }, { logoData });

  await page.waitForTimeout(300);
  await page.evaluate(() => {
    const brand = document.getElementById('af-brand');
    brand.style.opacity = '1';
    brand.style.transform = 'translateY(0)';
  });
  await page.waitForTimeout(2600);
  await page.evaluate(() => {
    const title = document.getElementById('af-title');
    title.style.opacity = '1';
    title.style.transform = 'translateY(0)';
  });
  await page.waitForTimeout(7100);
  await page.evaluate(() => document.getElementById('af-opening-card')?.remove());
  await page.waitForTimeout(400);
}

async function closingCard() {
  await page.evaluate(({ logoData }) => {
    const card = document.createElement('div');
    card.id = 'af-closing-card';
    card.style.cssText = [
      'position:fixed','inset:0','z-index:2147483647',
      'display:flex','align-items:center','justify-content:center',
      'background:#fff','font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
      'color:#111','text-align:center'
    ].join(';');
    card.innerHTML = `
      <div id="af-closing-brand" style="opacity:0;transform:translateY(12px);transition:opacity 1.2s ease,transform 1.2s ease">
        <img src="${logoData}" alt="ArtsFolio" style="display:block;max-width:980px;max-height:440px;width:auto;height:auto;margin:0 auto 34px;object-fit:contain">
        <div style="font-size:54px;letter-spacing:.08em;font-weight:700">artsfol.io</div>
      </div>`;
    document.body.appendChild(card);
  }, { logoData });
  await page.waitForTimeout(250);
  await page.evaluate(() => {
    const brand = document.getElementById('af-closing-brand');
    brand.style.opacity = '1';
    brand.style.transform = 'translateY(0)';
  });
  await page.waitForTimeout(5750);
}

async function perform(action) {
  await clearHighlights();

  switch (action) {
    case 'public_home':
      await goto('/');
      await highlight('main, .site-main');
      break;
    case 'login':
      await login();
      break;
    case 'highlight_site_name':
      await highlight('.tenant-admin-sidebar-title');
      break;
    case 'highlight_admin_main':
      await highlight('.tenant-admin-main');
      break;
    case 'highlight_sidebar':
      await goto('/admin');
      await highlight('.tenant-admin-sidebar');
      break;
    case 'dashboard_top':
      await goto('/admin');
      await highlight('.tenant-admin-main');
      break;
    case 'dashboard_scroll':
      await goto('/admin');
      await page.mouse.wheel(0, 700);
      await highlight('.tenant-admin-main');
      break;
    case 'public_portfolio':
      await goto('/portfolio');
      await highlight('main, .portfolio-grid');
      break;
    case 'public_mobile':
      await goto('/');
      await page.setViewportSize({ width: 900, height: 1080 });
      await highlight('main');
      break;
    case 'return_admin':
      await page.setViewportSize({ width: 1920, height: 1080 });
      await goto('/admin');
      await highlight('.tenant-admin-main');
      break;
    case 'onboarding_page':
      await goto('/admin/onboarding');
      await highlight('.tenant-admin-main');
      break;
    case 'onboarding_resources':
      await goto('/admin/onboarding');
      await highlight('.tenant-admin-main');
      break;
    case 'onboarding_reset':
      await goto('/admin/onboarding');
      await highlight('#tenant-onboarding-reset-form');
      break;
    case 'help_index':
      await goto('/help');
      await highlight('.tenant-admin-main');
      break;
    case 'help_functions':
      await goto('/help/tenant-admin-functions');
      await highlight('.tenant-admin-main');
      break;
    case 'help_videos':
      await goto('/help/training-videos');
      await highlight('.tenant-admin-main');
      break;
    case 'settings_page':
      await goto('/admin/settings');
      await highlight('.tenant-admin-main');
      break;
    case 'highlight_save':
      await goto('/admin/settings');
      await highlight('button[type="submit"]');
      break;
    case 'artworks_page':
      await goto('/admin/artworks');
      await highlight('.tenant-admin-main');
      break;
    case 'users_page':
      await goto('/admin/users');
      await highlight('.tenant-admin-main');
      break;
  }
}

await openingCard();

let ordinal = 0;
for (const scene of scenes) {
  for (const cue of scene.cues) {
    ordinal += 1;
    const key = `${String(ordinal).padStart(3, '0')}-${scene.id}-${cue.id}`;
    await perform(cue.action);
    const milliseconds = Math.max(1200, Math.round(Number(durations[key]) * 1000));
    await page.waitForTimeout(milliseconds);
  }
}

await clearHighlights();
await closingCard();

await context.close();
await browser.close();

const recorded = await video.path();
fs.copyFileSync(recorded, path.join(rawRoot, 'video01-browser.webm'));
console.log('[PASS] Browser recording complete.');
