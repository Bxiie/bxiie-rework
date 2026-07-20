import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { chromium } from 'playwright';

const videoRoot = path.dirname(new URL(import.meta.url).pathname);
const factoryRoot = path.resolve(videoRoot, '..');
const artsfolioRoot = path.resolve(factoryRoot, '..');
const workRoot = path.join(factoryRoot, 'work', 'video-01');
const rawRoot = path.join(workRoot, 'raw');
const cueStartsPath = path.join(workRoot, 'cue-starts.json');
const scenes = JSON.parse(fs.readFileSync(path.join(videoRoot, 'scenes.json'), 'utf8'));
const durations = JSON.parse(fs.readFileSync(path.join(workRoot, 'cue-durations.json'), 'utf8'));

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

if (!email || !password) throw new Error('Training login credentials are missing.');

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
const cueStarts = {};
const recordingStartedAt = performance.now();

function elapsedSeconds() {
  return (performance.now() - recordingStartedAt) / 1000;
}

async function assertHealthy(action) {
  const url = page.url();
  const title = await page.title().catch(() => '');
  const body = await page.locator('body').innerText().catch(() => '');
  const looksLike500 =
    /\b500\b/.test(title) ||
    /\b500\b/.test(body.slice(0, 500)) ||
    /internal server error/i.test(body.slice(0, 1000));

  if (looksLike500) {
    throw new Error(
      `[HTTP 500 GUARD] action=${action}; url=${url}; title=${title}`
    );
  }
}

async function ready(action) {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(550);
  await assertHealthy(action);
}

async function goto(route, action = route) {
  const response = await page.goto(
    new URL(route, baseUrl).toString(),
    { waitUntil: 'domcontentloaded' }
  );

  if (response && response.status() >= 500) {
    throw new Error(
      `[HTTP ${response.status()}] action=${action}; url=${response.url()}`
    );
  }

  await ready(action);
}

async function clickOrGoto(href, action) {
  const locator = page.locator(`a[href="${href}"]`).first();

  if (!(await locator.count())) {
    await goto(href, action);
    return;
  }

  await locator.scrollIntoViewIfNeeded();

  const navigation = page.waitForNavigation({
    waitUntil: 'domcontentloaded',
    timeout: 15000,
  }).catch(() => null);

  await locator.click();
  const response = await navigation;

  if (response && response.status() >= 500) {
    throw new Error(
      `[HTTP ${response.status()}] action=${action}; url=${response.url()}`
    );
  }

  await ready(action);
}

async function login() {
  await goto('/login', 'login-page');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);

  const navigation = page.waitForNavigation({
    waitUntil: 'domcontentloaded',
    timeout: 30000,
  });

  await page.locator('button[type="submit"]').click();
  const response = await navigation;

  if (response && response.status() >= 500) {
    throw new Error(
      `[HTTP ${response.status()}] action=login-submit; url=${response.url()}`
    );
  }

  await ready('login-submit');
}

async function openingCard() {
  await page.setContent(`<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
html,body{margin:0;width:100%;height:100%;background:#fff;overflow:hidden}
body{display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111;text-align:center}
#brand{opacity:0;transform:translateY(14px);transition:opacity 1.4s ease,transform 1.4s ease}
#title{opacity:0;transform:translateY(18px);transition:opacity 1.2s ease,transform 1.2s ease;margin-top:54px}
</style>
</head>
<body>
<div style="max-width:1300px;padding:70px">
  <div id="brand">
    <img src="${logoData}" alt="ArtsFolio" style="display:block;max-width:980px;max-height:430px;width:auto;height:auto;margin:0 auto 34px;object-fit:contain">
    <div style="font-size:54px;letter-spacing:.08em;font-weight:700">artsfol.io</div>
  </div>
  <div id="title">
    <div style="font-size:30px;letter-spacing:.15em;text-transform:uppercase;font-weight:700">Training Video 01</div>
    <div style="font-size:64px;line-height:1.08;font-weight:800;margin-top:18px">Your Admin Orientation</div>
  </div>
</div>
</body>
</html>`);

  await page.waitForTimeout(250);
  await page.evaluate(() => {
    const brand = document.getElementById('brand');
    brand.style.opacity = '1';
    brand.style.transform = 'translateY(0)';
  });
  await page.waitForTimeout(2600);
  await page.evaluate(() => {
    const title = document.getElementById('title');
    title.style.opacity = '1';
    title.style.transform = 'translateY(0)';
  });
  await page.waitForTimeout(4150);
}

async function closingCard() {
  await page.setContent(`<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
html,body{margin:0;width:100%;height:100%;background:#fff;overflow:hidden}
body{display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111;text-align:center}
#brand{opacity:0;transform:translateY(12px);transition:opacity 1.2s ease,transform 1.2s ease}
</style>
</head>
<body>
<div id="brand">
  <img src="${logoData}" alt="ArtsFolio" style="display:block;max-width:980px;max-height:440px;width:auto;height:auto;margin:0 auto 34px;object-fit:contain">
  <div style="font-size:54px;letter-spacing:.08em;font-weight:700">artsfol.io</div>
</div>
</body>
</html>`);

  await page.waitForTimeout(250);
  await page.evaluate(() => {
    const brand = document.getElementById('brand');
    brand.style.opacity = '1';
    brand.style.transform = 'translateY(0)';
  });
  await page.waitForTimeout(5750);
}

async function ensureDesktopViewport() {
  const viewport = page.viewportSize();

  if (
    viewport === null
    || viewport.width !== 1920
    || viewport.height !== 1080
  ) {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(250);
  }
}

async function perform(action) {
  // The mobile demonstration is the only cue allowed to use a narrow viewport.
  // Reset before every other cue so later admin pages fill the 1920px recording.
  if (action !== 'public_mobile') {
    await ensureDesktopViewport();
  }

  switch (action) {
    case 'public_home': await goto('/', action); break;
    case 'login': await login(); break;
    case 'show_dashboard': await goto('/admin', action); break;
    case 'click_dashboard': await clickOrGoto('/admin', action); break;
    case 'click_content': await clickOrGoto('/admin/content', action); break;
    case 'click_artworks': await clickOrGoto('/admin/artworks', action); break;
    case 'click_upload_artwork': await goto('/admin/artwork/upload', action); break;
    case 'click_portfolio_sections': await clickOrGoto('/admin/portfolio-sections', action); break;
    case 'click_events': await clickOrGoto('/admin/events', action); break;
    case 'click_messages': await clickOrGoto('/admin/contact-messages', action); break;
    case 'click_email_signups': await clickOrGoto('/admin/email-signups', action); break;
    case 'click_sales': await clickOrGoto('/admin/sales', action); break;
    case 'click_stats': await clickOrGoto('/admin/stats', action); break;
    case 'click_settings': await clickOrGoto('/admin/settings', action); break;
    case 'click_users': await clickOrGoto('/admin/users', action); break;
    case 'click_domains': await clickOrGoto('/admin/domains', action); break;
    case 'click_billing': await clickOrGoto('/admin/billing', action); break;
    case 'click_onboarding': await clickOrGoto('/admin/onboarding', action); break;
    case 'click_help': await clickOrGoto('/help', action); break;
    case 'dashboard_scroll':
      await goto('/admin', action);
      await page.mouse.wheel(0, 700);
      await page.waitForTimeout(250);
      break;
    case 'public_portfolio': await goto('/portfolio', action); break;
    case 'public_mobile':
      await goto('/', action);
      await page.setViewportSize({ width: 900, height: 1080 });
      await page.waitForTimeout(250);
      break;
    case 'show_onboarding': await goto('/admin/onboarding', action); break;
    case 'show_onboarding_reset':
      await goto('/admin/onboarding', action);
      {
        const reset = page.locator('#tenant-onboarding-reset-form').first();
        if (await reset.count()) await reset.scrollIntoViewIfNeeded();
      }
      break;
    case 'help_functions': await goto('/help/tenant-admin-functions', action); break;
    case 'help_videos': await goto('/help/training-videos', action); break;
    case 'show_save':
      await goto('/admin/settings', action);
      {
        const save = page.locator('button[type="submit"]').first();
        if (await save.count()) await save.scrollIntoViewIfNeeded();
      }
      break;
    default:
      throw new Error(`Unknown video action: ${action}`);
  }
}

try {
  await openingCard();

  let ordinal = 0;
  for (const scene of scenes) {
    for (const cue of scene.cues) {
      ordinal += 1;
      const key = `${String(ordinal).padStart(3, '0')}-${scene.id}-${cue.id}`;

      await perform(cue.action);

      // This timestamp is captured only after the destination page is ready.
      cueStarts[key] = elapsedSeconds();

      const holdMilliseconds = Math.max(
        1200,
        Math.round(Number(durations[key]) * 1000)
      );
      await page.waitForTimeout(holdMilliseconds);
    }
  }

  await closingCard();
  fs.writeFileSync(cueStartsPath, JSON.stringify(cueStarts, null, 2) + '\n');
} catch (error) {
  fs.writeFileSync(
    path.join(workRoot, 'video01-recording-error.txt'),
    `${error.stack || error}\n`,
  );
  throw error;
} finally {
  await context.close();
  await browser.close();
}

const recorded = await video.path();
fs.copyFileSync(recorded, path.join(rawRoot, 'video01-browser.webm'));
console.log('[PASS] Browser recording complete with timestamped narration cues.');
