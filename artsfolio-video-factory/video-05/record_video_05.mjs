import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { chromium } from "playwright";

const videoRoot = path.dirname(new URL(import.meta.url).pathname);
const factoryRoot = path.resolve(videoRoot, "..");
const artsfolioRoot = path.resolve(factoryRoot, "..");
const workRoot = path.join(factoryRoot, "work", "video-05");
const rawRoot = path.join(workRoot, "raw");
const scenes = JSON.parse(fs.readFileSync(path.join(videoRoot, "scenes.json"), "utf8"));
const durationsPath = path.join(workRoot, "cue-durations.json");
const durations = fs.existsSync(durationsPath)
  ? JSON.parse(fs.readFileSync(durationsPath, "utf8"))
  : {};
const startsPath = path.join(workRoot, "cue-starts.json");

const baseUrl = process.env.AF_VIDEO_BASE_URL || "https://training.artsfol.io";
const email = process.env.AF_VIDEO_EMAIL || "";
const password = process.env.AF_VIDEO_PASSWORD || "";
const preflightOnly = (process.env.AF_VIDEO_PREFLIGHT_ONLY || "false") === "true";
if (!email || !password) throw new Error("Training login credentials are missing.");

const logoPath = [
  path.join(artsfolioRoot, "public", "assets", "logo_2.png"),
  path.join(artsfolioRoot, "public", "assets", "logo.png"),
].find(candidate => fs.existsSync(candidate));
if (!logoPath) throw new Error("ArtsFolio logo was not found.");
const logoData = `data:image/png;base64,${fs.readFileSync(logoPath).toString("base64")}`;

fs.mkdirSync(rawRoot, { recursive: true });

const browser = await chromium.launch({
  headless: true,
  args: ["--disable-notifications", "--disable-infobars", "--hide-scrollbars"],
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
const issues = [];
const issueKeys = new Set();
const startedAt = performance.now();

const elapsed = () => (performance.now() - startedAt) / 1000;

function reportIssue(key, action, detail) {
  const normalized = String(detail || "Unknown issue").replace(/\s+/g, " ").trim();
  const signature = `${key}|${action}|${normalized}`;
  if (issueKeys.has(signature)) return;
  issueKeys.add(signature);
  issues.push({ key, action, detail: normalized, url: page.url() });
}

async function desktop() {
  const size = page.viewportSize();
  if (!size || size.width !== 1920 || size.height !== 1080) {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(250);
  }
}

async function healthy(action) {
  const title = await page.title().catch(() => "");
  const body = await page.locator("body").innerText().catch(() => "");
  if (/\b500\b/.test(title) || /internal server error/i.test(body.slice(0, 1200))) {
    throw new Error(`[500 GUARD] ${action}: ${page.url()}`);
  }
}

async function go(route, action = route) {
  const response = await page.goto(
    new URL(route, baseUrl).toString(),
    { waitUntil: "domcontentloaded" }
  );
  if (response && response.status() >= 500) {
    throw new Error(`[HTTP ${response.status()}] ${action}: ${response.url()}`);
  }
  await page.waitForTimeout(650);
  await healthy(action);
}

async function login() {
  await go("/login", "login");
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await Promise.all([
    page.waitForURL(url => url.pathname.startsWith("/admin"), { timeout: 30000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  await page.waitForTimeout(650);
  await healthy("login-submit");
}

async function target(selector) {
  const selectors = selector.split(",").map(item => item.trim()).filter(Boolean);
  for (const candidate of selectors) {
    const item = page.locator(`${candidate}:visible`).first();
    if (!(await item.count())) continue;
    await item.scrollIntoViewIfNeeded({ timeout: 2500 });
    await page.waitForTimeout(250);
    return true;
  }
  throw new Error(`No visible target: ${selector}`);
}

async function titleCard() {
  await page.setContent(`<!doctype html><html><body style="margin:0;background:white;width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;font-family:Arial,sans-serif"><div><img src="${logoData}" style="display:block;max-width:900px;max-height:390px;margin:0 auto 28px"><div style="font-size:50px;font-weight:700;letter-spacing:.08em">artsfol.io</div><div style="font-size:28px;margin-top:40px;font-weight:700;letter-spacing:.14em">TRAINING VIDEO 05</div><div style="font-size:60px;font-weight:800;line-height:1.08;margin-top:16px">Messages and Email Signups</div></div></body></html>`);
  await page.waitForTimeout(7000);
}

async function endCard() {
  await page.setContent(`<!doctype html><html><body style="margin:0;background:white;width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;font-family:Arial,sans-serif"><div><img src="${logoData}" style="display:block;max-width:900px;max-height:410px;margin:0 auto 28px"><div style="font-size:50px;font-weight:700;letter-spacing:.08em">artsfol.io</div></div></body></html>`);
  await page.waitForTimeout(6000);
}

async function perform(action) {
  if (action !== "public_contact_mobile") await desktop();

  switch (action) {
    case "messages_page":
      await go("/admin/contact-messages", action);
      break;
    case "messages_list":
      await go("/admin/contact-messages", action);
      await target("table,.tenant-admin-main,[class*=message]");
      break;
    case "messages_clear":
      await go("/admin/contact-messages", action);
      await target('a:has-text("Clear"),button:has-text("Clear"),form[action*="clear"]');
      break;
    case "messages_export":
      await go("/admin/contact-messages", action);
      await target('a[href$=".csv"],a:has-text("Export CSV"),button:has-text("Export CSV")');
      break;
    case "public_contact":
      await go("/contact", action);
      break;
    case "contact_form":
      await go("/contact", action);
      await target("form,input[name*=email],textarea");
      break;
    case "contact_content":
      await go("/contact", action);
      await target("main,.contact,[class*=contact]");
      break;
    case "contact_submit":
      await go("/contact", action);
      await target('button[type="submit"],input[type="submit"]');
      break;
    case "signups_page":
      await go("/admin/email-signups", action);
      break;
    case "signups_list":
      await go("/admin/email-signups", action);
      await target("table,.tenant-admin-main,[class*=signup]");
      break;
    case "signups_status":
      await go("/admin/email-signups", action);
      await target("select[name*=status],input[name*=status],[class*=status],table");
      break;
    case "signups_controls":
      await go("/admin/email-signups", action);
      await target("button,a[download],form,.tenant-admin-main");
      break;
    case "public_home":
      await go("/", action);
      break;
    case "public_signup_form":
      await go("/", action);
      await target('form[action*="signup"],input[type="email"],[class*=signup]');
      break;
    case "settings_identity":
      await go("/admin/settings?section=identity", action);
      break;
    case "public_contact_mobile":
      await go("/contact", action);
      await page.setViewportSize({ width: 900, height: 1080 });
      await page.waitForTimeout(250);
      break;
    default:
      throw new Error(`Unknown action: ${action}`);
  }
}

try {
  if (!preflightOnly) await titleCard();
  await login();

  let ordinal = 0;
  for (const scene of scenes) {
    for (const cue of scene.cues) {
      ordinal += 1;
      const key = `${String(ordinal).padStart(3, "0")}-${scene.id}-${cue.id}`;
      try {
        await perform(cue.action);
        if (preflightOnly) {
          console.log(`[PREFLIGHT VISITED] ${key} -> ${cue.action}`);
        } else {
          cueStarts[key] = elapsed();
          await page.waitForTimeout(
            Math.max(1200, Math.round(Number(durations[key] || 1.2) * 1000))
          );
        }
      } catch (error) {
        if (!preflightOnly) throw error;
        reportIssue(key, cue.action, error?.message || error);
        console.error(`[PREFLIGHT ISSUE] ${key}: ${error?.message || error}`);
      }
    }
  }

  if (preflightOnly) {
    console.log("\n========== VIDEO 05 PREFLIGHT REPORT ==========");
    if (issues.length) {
      console.error(`[FAIL] ${issues.length} issue(s) found:`);
      issues.forEach((issue, index) => {
        console.error(`${index + 1}. ${issue.key} -> ${issue.action}`);
        console.error(`   ${issue.detail}`);
        console.error(`   ${issue.url}`);
      });
      console.error("================================================");
      throw new Error(`Video 05 preflight found ${issues.length} issue(s).`);
    }
    console.log("[PASS] Every route and required control was found.");
    console.log("================================================");
  } else {
    await endCard();
    fs.writeFileSync(startsPath, JSON.stringify(cueStarts, null, 2) + "\n");
  }
} finally {
  await context.close();
  await browser.close();
}

if (!preflightOnly) {
  fs.copyFileSync(await video.path(), path.join(rawRoot, "video05-browser.webm"));
  console.log("[PASS] Video 05 browser recording complete.");
}
