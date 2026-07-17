import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { chromium } from 'playwright';

const factoryRoot = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const workRoot = path.join(factoryRoot, 'work', 'video-01');
const rawRoot = path.join(workRoot, 'raw');
const scenes = JSON.parse(fs.readFileSync(path.join(factoryRoot, 'video-01', 'scenes.json'), 'utf8'));
const durations = JSON.parse(fs.readFileSync(path.join(workRoot, 'scene-durations.json'), 'utf8'));

fs.mkdirSync(rawRoot, { recursive: true });

const baseUrl = process.env.AF_VIDEO_BASE_URL || 'https://training.artsfol.io';
const email = process.env.AF_VIDEO_EMAIL || '';
const password = process.env.AF_VIDEO_PASSWORD || '';
const headless = (process.env.AF_VIDEO_HEADLESS || 'true').toLowerCase() !== 'false';

if (!email || !password) {
  throw new Error('AF_VIDEO_EMAIL and AF_VIDEO_PASSWORD are required.');
}

const browser = await chromium.launch({
  headless,
  args: [
    '--disable-notifications',
    '--disable-infobars',
    '--hide-scrollbars',
    '--force-device-scale-factor=1',
  ],
});

const context = await browser.newContext({
  viewport: { width: 1920, height: 1080 },
  screen: { width: 1920, height: 1080 },
  deviceScaleFactor: 1,
  recordVideo: {
    dir: rawRoot,
    size: { width: 1920, height: 1080 },
  },
  colorScheme: 'light',
});

const page = await context.newPage();
const video = page.video();

page.on('dialog', async dialog => {
  await dialog.dismiss();
});

async function waitReady(target = page) {
  await target.waitForLoadState('domcontentloaded');
  await target.waitForTimeout(900);
}

async function goto(relativePath) {
  await page.goto(new URL(relativePath, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  await waitReady();
}

async function overlay(title, subtitle = '', milliseconds = 2200) {
  await page.evaluate(({ title, subtitle }) => {
    document.getElementById('af-video-overlay')?.remove();
    const overlay = document.createElement('div');
    overlay.id = 'af-video-overlay';
    overlay.style.cssText = [
      'position:fixed',
      'inset:0',
      'z-index:2147483647',
      'display:flex',
      'align-items:center',
      'justify-content:center',
      'background:rgba(15,17,20,.86)',
      'color:white',
      'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
      'text-align:center',
      'padding:80px'
    ].join(';');
    overlay.innerHTML = `
      <div style="max-width:1250px">
        <div style="font-size:30px;letter-spacing:.12em;text-transform:uppercase;opacity:.72;margin-bottom:22px">ArtsFolio Training</div>
        <div style="font-size:82px;line-height:1.06;font-weight:800">${title}</div>
        ${subtitle ? `<div style="font-size:38px;line-height:1.25;margin-top:24px;opacity:.86">${subtitle}</div>` : ''}
      </div>`;
    document.body.appendChild(overlay);
  }, { title, subtitle });
  await page.waitForTimeout(milliseconds);
  await page.evaluate(() => document.getElementById('af-video-overlay')?.remove());
  await page.waitForTimeout(500);
}

async function highlight(selector, milliseconds = 1600) {
  const locator = page.locator(selector).first();
  if (!(await locator.count())) return;
  await locator.scrollIntoViewIfNeeded();
  await locator.evaluate(element => {
    element.dataset.afVideoOldOutline = element.style.outline || '';
    element.dataset.afVideoOldOffset = element.style.outlineOffset || '';
    element.style.outline = '6px solid #f4b942';
    element.style.outlineOffset = '6px';
    element.style.transition = 'outline .2s ease';
  });
  await page.waitForTimeout(milliseconds);
  await locator.evaluate(element => {
    element.style.outline = element.dataset.afVideoOldOutline || '';
    element.style.outlineOffset = element.dataset.afVideoOldOffset || '';
    delete element.dataset.afVideoOldOutline;
    delete element.dataset.afVideoOldOffset;
  });
}

async function spendScene(sceneId, startedAt) {
  const totalMs = Math.max(2500, Math.round(Number(durations[sceneId] || 4) * 1000));
  const elapsed = Date.now() - startedAt;
  const remainder = Math.max(300, totalMs - elapsed);
  await page.waitForTimeout(remainder);
}

async function login() {
  await goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  const keep = page.locator('input[name="keep_me_logged_in"]');
  if (await keep.count()) await keep.check();
  await Promise.all([
    page.waitForURL(url => url.pathname.startsWith('/admin'), { timeout: 30000 }),
    page.locator('form.auth-form button[type="submit"]').click(),
  ]);
  await waitReady();
}

for (const scene of scenes) {
  const startedAt = Date.now();

  switch (scene.id) {
    case '01-opening':
      await goto('/');
      await overlay(scene.title, scene.subtitle, 3600);
      await highlight('header, .site-header', 1200);
      break;

    case '02-confirm-site':
      await login();
      await overlay(scene.title, scene.subtitle, 1800);
      await highlight('.tenant-admin-sidebar-title', 3500);
      break;

    case '03-sidebar': {
      await goto('/admin');
      await overlay(scene.title, scene.subtitle, 1500);
      const links = [
        '/admin',
        '/admin/onboarding',
        '/admin/settings',
        '/admin/content',
        '/admin/artworks',
        '/admin/portfolio-sections',
        '/admin/events',
        '/admin/contact-messages',
        '/admin/email-signups',
        '/admin/sales',
        '/admin/users',
        '/admin/stats',
      ];
      for (const href of links) {
        await highlight(`.tenant-admin-sidebar a[href="${href}"]`, 650);
      }
      break;
    }

    case '04-dashboard':
      await goto('/admin');
      await overlay(scene.title, scene.subtitle, 1500);
      await highlight('.tenant-admin-main', 1800);
      await page.mouse.wheel(0, 650);
      await page.waitForTimeout(1000);
      await page.mouse.wheel(0, -650);
      break;

    case '05-preview':
      await goto('/');
      await overlay(scene.title, scene.subtitle, 1500);
      await highlight('header nav a[href="/portfolio"], header nav a[href*="portfolio"]', 1800);
      {
        const portfolioLink = page.locator('a[href="/portfolio"], a[href$="/portfolio"]').first();
        if (await portfolioLink.count()) {
          await portfolioLink.click();
          await waitReady();
        } else {
          await goto('/portfolio');
        }
      }
      await page.mouse.wheel(0, 620);
      await page.waitForTimeout(1600);
      await goto('/admin');
      break;

    case '06-onboarding':
      await goto('/admin/onboarding');
      await overlay(scene.title, scene.subtitle, 1500);
      await highlight('a[href="/admin/getting-started"], a[href="/help/new-admin-tour"]', 1500);
      await highlight('#tenant-onboarding-reset-form', 1800);
      break;

    case '07-help':
      await goto('/help');
      await overlay(scene.title, scene.subtitle, 1500);
      await page.mouse.wheel(0, 500);
      await page.waitForTimeout(1200);
      {
        const functionLink = page.locator('a[href="/help/tenant-admin-functions"]').first();
        if (await functionLink.count()) {
          await functionLink.click();
          await waitReady();
          await page.mouse.wheel(0, 420);
          await page.waitForTimeout(1200);
        }
      }
      {
        const videoLink = page.locator('a[href="/help/training-videos"]').first();
        if (await videoLink.count()) {
          await videoLink.click();
          await waitReady();
        } else {
          await goto('/help/training-videos');
        }
      }
      break;

    case '08-save-verify':
      await goto('/admin/settings');
      await overlay(scene.title, scene.subtitle, 1500);
      await highlight('button[type="submit"]', 2200);
      await goto('/');
      await page.setViewportSize({ width: 900, height: 1080 });
      await page.waitForTimeout(1800);
      await page.setViewportSize({ width: 1920, height: 1080 });
      break;

    case '09-privacy':
      await goto('/admin/users');
      await overlay(scene.title, scene.subtitle, 1500);
      await highlight('.tenant-admin-main', 1500);
      await goto('/admin');
      break;

    case '10-closing':
      await goto('/admin');
      await overlay(scene.title, scene.subtitle, 4600);
      break;
  }

  await spendScene(scene.id, startedAt);
}

await context.close();
await browser.close();

const recordedPath = await video.path();
const destination = path.join(rawRoot, 'video01-browser.webm');
fs.copyFileSync(recordedPath, destination);
console.log(destination);
