import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { chromium } from 'playwright';

const videoRoot = path.dirname(new URL(import.meta.url).pathname);
const factoryRoot = path.resolve(videoRoot, '..');
const artsfolioRoot = path.resolve(factoryRoot, '..');
const workRoot = path.join(factoryRoot, 'work', 'video-03');
const rawRoot = path.join(workRoot, 'raw');
const scenes = JSON.parse(fs.readFileSync(path.join(videoRoot, 'scenes.json'), 'utf8'));
const durationsPath = path.join(workRoot, 'cue-durations.json');
const durations = fs.existsSync(durationsPath)
  ? JSON.parse(fs.readFileSync(durationsPath, 'utf8'))
  : {};
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
const preflightOnly = (process.env.AF_VIDEO_PREFLIGHT_ONLY || 'false') === 'true';
const strictTargets = (process.env.AF_VIDEO_STRICT_TARGETS || 'true') !== 'false';
const preflightIssues = [];
const preflightIssueKeys = new Set();

function reportPreflightIssue(kind, cueKey, action, detail) {
  const normalizedDetail = String(detail || 'Unknown issue')
    .replace(/\s+/g, ' ')
    .trim();
  const issueKey = `${kind}|${cueKey}|${action}|${normalizedDetail}`;

  if (preflightIssueKeys.has(issueKey)) {
    return;
  }

  preflightIssueKeys.add(issueKey);
  preflightIssues.push({
    kind,
    cueKey,
    action,
    detail: normalizedDetail,
    url: page?.url?.() || '',
  });
}
if (!email || !password) throw new Error('Training login credentials are missing.');

fs.mkdirSync(rawRoot, { recursive: true });

const browser = await chromium.launch({
  headless,
  args: ['--disable-notifications','--disable-infobars','--hide-scrollbars'],
});
const contextOptions = {
  viewport: { width: 1920, height: 1080 },
  deviceScaleFactor: 1,
};

if (!preflightOnly) {
  contextOptions.recordVideo = {
    dir: rawRoot,
    size: { width: 1920, height: 1080 },
  };
}

const context = await browser.newContext(contextOptions);
const page = await context.newPage();
const video = preflightOnly ? null : page.video();
const cueStarts = {};
const startedAt = performance.now();
let firstArtworkAdminUrl = null;
let firstArtworkPublicUrl = null;

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
  if (/\b500\b/.test(title) || /internal server error/i.test(body.slice(0, 1200))) {
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
  const selectors = selector.split(',').map(item => item.trim()).filter(Boolean);
  for (const candidate of selectors) {
    const target = page.locator(`${candidate}:visible`).first();
    if (!(await target.count())) continue;
    try {
      await target.scrollIntoViewIfNeeded({ timeout: 2500 });
      await page.waitForTimeout(250);
      return true;
    } catch {}
  }
  const message = `[VIDEO 03] No visible scroll target found: ${selector}`;

  if (preflightOnly) {
    reportPreflightIssue(
      'missing-control',
      globalThis.currentCueKey || 'unknown-cue',
      globalThis.currentCueAction || 'unknown-action',
      message
    );
    return false;
  }

  if (preflightOnly) {
    reportPreflightIssue(
      'missing-control',
      globalThis.currentCueKey || 'unknown-cue',
      globalThis.currentCueAction || 'unknown-action',
      message
    );
    return false;
  }

  if (strictTargets) {
    throw new Error(message);
  }

  console.warn(message);
  return false;
}

async function scrollToLabelText(labels) {
  for (const labelText of labels) {
    const label = page.getByText(labelText, {
      exact: true,
    }).filter({ visible: true }).first();

    if (!(await label.count())) {
      continue;
    }

    try {
      const container = label.locator(
        'xpath=ancestor::*[self::label or self::fieldset or contains(@class,"field") or contains(@class,"form-group")][1]'
      );

      if (await container.count()) {
        await container.scrollIntoViewIfNeeded({ timeout: 2500 });
      } else {
        await label.scrollIntoViewIfNeeded({ timeout: 2500 });
      }

      await page.waitForTimeout(250);
      return true;
    } catch {}
  }

  const message =
    `[VIDEO 03] No visible label target found: ${labels.join(', ')}`;

  if (preflightOnly) {
    reportPreflightIssue(
      'missing-control',
      globalThis.currentCueKey || 'unknown-cue',
      globalThis.currentCueAction || 'unknown-action',
      message
    );
    return false;
  }

  if (preflightOnly) {
    reportPreflightIssue(
      'missing-control',
      globalThis.currentCueKey || 'unknown-cue',
      globalThis.currentCueAction || 'unknown-action',
      message
    );
    return false;
  }

  if (strictTargets) {
    throw new Error(message);
  }

  console.warn(message);
  return false;
}

async function discoverFirstArtwork() {
  firstArtworkAdminUrl = '/admin/artworks/edit?id=94168';

  console.log(
    `[VIDEO 03] Pinned training artwork: ${firstArtworkAdminUrl}`
  );

  await goto(firstArtworkAdminUrl, 'verify-pinned-training-artwork');

  const destination = new URL(page.url());

  if (
    destination.pathname === '/admin/artwork/upload'
    || destination.pathname.endsWith('/upload')
  ) {
    throw new Error(
      `Pinned artwork redirected to Upload Artwork: ` +
      `${destination.pathname}${destination.search}`
    );
  }

  if (
    destination.pathname !== '/admin/artworks/edit'
    || destination.searchParams.get('id') !== '94168'
  ) {
    throw new Error(
      `Pinned artwork opened an unexpected destination: ` +
      `${destination.pathname}${destination.search}`
    );
  }

  await healthy('verify-pinned-training-artwork');

  await goto('/portfolio', 'discover-public-artwork');

  const publicCandidates = page.locator(
    'a[href*="/artwork/"]:visible, ' +
    'a[href*="/portfolio/"]:visible'
  );

  const publicCount = await publicCandidates.count();

  for (let index = 0; index < publicCount; index += 1) {
    const candidate = publicCandidates.nth(index);
    const href = await candidate.getAttribute('href');

    if (!href) {
      continue;
    }

    const url = new URL(href, baseUrl);

    if (
      url.pathname === '/portfolio'
      || url.pathname.endsWith('/upload')
    ) {
      continue;
    }

    firstArtworkPublicUrl = url.pathname + url.search;
    break;
  }

  if (!firstArtworkPublicUrl) {
    console.warn(
      '[VIDEO 03] No public artwork detail link was found. ' +
      'The public-detail cue will remain on the Portfolio page.'
    );
  }
}

async function titleCard() {
  await page.setContent(`<!doctype html><html><head><meta charset="utf-8"><style>
html,body{margin:0;width:100%;height:100%;background:#fff;overflow:hidden}
body{display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111;text-align:center}
#brand{opacity:0;transform:translateY(14px);transition:opacity 1.4s ease,transform 1.4s ease}
#title{opacity:0;transform:translateY(18px);transition:opacity 1.2s ease,transform 1.2s ease;margin-top:44px}
</style></head><body><div style="max-width:1350px;padding:60px">
<div id="brand"><img src="${logoData}" style="display:block;max-width:900px;max-height:390px;margin:0 auto 28px;object-fit:contain"><div style="font-size:50px;letter-spacing:.08em;font-weight:700">artsfol.io</div></div>
<div id="title"><div style="font-size:28px;letter-spacing:.15em;text-transform:uppercase;font-weight:700">Training Video 03</div><div style="font-size:60px;line-height:1.08;font-weight:800;margin-top:16px">Artwork and Portfolio Management</div></div>
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
async function openFirstArtwork() {
  if (!firstArtworkAdminUrl) {
    await goto('/admin/artworks', 'open-first-artwork-fallback');
    return;
  }
  await goto(firstArtworkAdminUrl, 'open-first-artwork');
}
async function perform(action) {
  if (action !== 'public_mobile_portfolio') await desktop();
  switch (action) {
    case 'artworks_page': await goto('/admin/artworks', action); break;
    case 'show_artwork_filters': await goto('/admin/artworks', action); await scrollTo('form[action="/admin/artworks"], input[name="q"]'); break;
    case 'show_type_filter': await goto('/admin/artworks', action); await scrollTo('select[name="type"], select[name="artwork_type"]'); break;
    case 'open_first_artwork': await openFirstArtwork(); break;
    case 'upload_page': await goto('/admin/artwork/upload', action); break;
    case 'show_upload_image': await goto('/admin/artwork/upload', action); await scrollTo('input[type="file"]'); break;
    case 'show_upload_identity': await goto('/admin/artwork/upload', action); await scrollTo('input[name="title"], input[name="name"]'); break;
    case 'show_upload_details': await goto('/admin/artwork/upload', action); await scrollTo('textarea[name="description"], input[name="medium"]'); break;
    case 'show_upload_save': await goto('/admin/artwork/upload', action); await scrollTo('button[type="submit"]'); break;
    case 'show_edit_metadata': await openFirstArtwork(); await scrollTo('input[name="title"], input[name="name"]'); break;
    case 'show_publication_controls':
      await openFirstArtwork();
      await scrollTo(
        'button:has-text("Publish"), ' +
        'button:has-text("Unpublish"), ' +
        'button:has-text("Draft"), ' +
        'a:has-text("Publish"), ' +
        'a:has-text("Unpublish"), ' +
        'select[name*="status"], ' +
        'input[name*="publish"], ' +
        'input[value="published"], ' +
        '[data-action*="publish"], ' +
        '[class*="publication"]'
      );
      break;
    case 'show_homepage_control':
      await openFirstArtwork();
      if (!(await scrollToLabelText(['Home Page', 'Show on Home Page', 'Homepage']))) {
        await scrollTo(
          'input[name*="home"], input[id*="home"], label:has-text("Home Page")'
        );
      }
      break;
    case 'show_sale_controls': await openFirstArtwork(); await scrollTo('input[name="for_sale"], input[name="price"], input[name="price_cents"]'); break;
    case 'show_edit_save': await openFirstArtwork(); await scrollTo('button[type="submit"]'); break;
    case 'sections_page': await goto('/admin/portfolio-sections', action); break;
    case 'show_sections_list': await goto('/admin/portfolio-sections', action); await scrollTo('.tenant-admin-main'); break;
    case 'show_sections_order': await goto('/admin/portfolio-sections', action); await scrollTo('[draggable="true"], button'); break;
    case 'site_images_page': await goto('/admin/artworks?type=site_images', action); break;
    case 'public_portfolio': await goto('/portfolio', action); break;
    case 'public_first_artwork':
      if (firstArtworkPublicUrl) await goto(firstArtworkPublicUrl, action);
      else await goto('/portfolio', action);
      break;
    case 'public_home': await goto('/', action); break;
    case 'public_mobile_portfolio':
      await goto('/portfolio', action);
      await page.setViewportSize({ width: 900, height: 1080 });
      await page.waitForTimeout(250);
      break;
    default: throw new Error(`Unknown action: ${action}`);
  }
}

try {
  if (!preflightOnly) {
    await titleCard();
  }

  await login();
  await discoverFirstArtwork();
  let ordinal=0;
  for (const scene of scenes) {
    for (const cue of scene.cues) {
      ordinal += 1;
      const key=`${String(ordinal).padStart(3,'0')}-${scene.id}-${cue.id}`;
      globalThis.currentCueKey = key;
      globalThis.currentCueAction = cue.action;

      try {
        await perform(cue.action);

        if (preflightOnly) {
          console.log(`[PREFLIGHT VISITED] ${key} -> ${cue.action}`);
          continue;
        }

        cueStarts[key] = elapsed();
        await page.waitForTimeout(
          Math.max(
            1200,
            Math.round(Number(durations[key] || 1.2) * 1000)
          )
        );
      } catch (error) {
        if (!preflightOnly) {
          throw error;
        }

        reportPreflightIssue(
          'route-or-action',
          key,
          cue.action,
          error?.message || error
        );

        console.error(
          `[PREFLIGHT ISSUE] ${key} -> ${cue.action}: ` +
          `${error?.message || error}`
        );
      }
    }
  }
  if (preflightOnly) {
    console.log('');
    console.log('========== VIDEO 03 PREFLIGHT REPORT ==========');

    if (preflightIssues.length === 0) {
      console.log('[PASS] Every route and required control was found.');
      console.log('================================================');
    } else {
      console.error(
        `[FAIL] ${preflightIssues.length} preflight issue(s) found:`
      );

      preflightIssues.forEach((issue, index) => {
        console.error(
          `${index + 1}. [${issue.kind}] ` +
          `${issue.cueKey} -> ${issue.action}`
        );
        console.error(`   ${issue.detail}`);

        if (issue.url) {
          console.error(`   URL: ${issue.url}`);
        }
      });

      console.error('================================================');
      throw new Error(
        `Video 03 browser preflight found ` +
        `${preflightIssues.length} issue(s).`
      );
    }
  } else {
    await endCard();
    fs.writeFileSync(
      cueStartsPath,
      JSON.stringify(cueStarts, null, 2) + '\n'
    );
  }
} catch (error) {
  fs.writeFileSync(path.join(workRoot,'video03-recording-error.txt'),`${error.stack||error}\n`);
  throw error;
} finally {
  await context.close();
  await browser.close();
}
if (preflightOnly) {
  console.log('[PASS] Video 03 browser preflight complete.');
} else {
  fs.copyFileSync(
    await video.path(),
    path.join(rawRoot, 'video03-browser.webm')
  );
  console.log('[PASS] Video 03 browser recording complete.');
}
