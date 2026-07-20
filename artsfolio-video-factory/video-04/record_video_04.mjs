import fs from "node:fs"; import path from "node:path"; import process from "node:process"; import { chromium } from "playwright";
const vr=path.dirname(new URL(import.meta.url).pathname), fr=path.resolve(vr,".."), ar=path.resolve(fr,".."), wr=path.join(fr,"work","video-04"), rr=path.join(wr,"raw");
const scenes=JSON.parse(fs.readFileSync(path.join(vr,"scenes.json"),"utf8")); const dp=path.join(wr,"cue-durations.json"); const durations=fs.existsSync(dp)?JSON.parse(fs.readFileSync(dp,"utf8")):{};
const base=process.env.AF_VIDEO_BASE_URL||"https://training.artsfol.io", email=process.env.AF_VIDEO_EMAIL||"", password=process.env.AF_VIDEO_PASSWORD||"", preflight=(process.env.AF_VIDEO_PREFLIGHT_ONLY||"false")==="true";
const logo=[path.join(ar,"public","assets","logo_2.png"),path.join(ar,"public","assets","logo.png")].find(fs.existsSync); if(!logo)throw new Error("Logo missing"); const logoData=`data:image/png;base64,${fs.readFileSync(logo).toString("base64")}`;
fs.mkdirSync(rr,{recursive:true}); const browser=await chromium.launch({headless:true,args:["--hide-scrollbars"]}); const opts={viewport:{width:1920,height:1080},deviceScaleFactor:1}; if(!preflight)opts.recordVideo={dir:rr,size:{width:1920,height:1080}};
const context=await browser.newContext(opts), page=await context.newPage(), video=preflight?null:page.video(), starts={}, issues=[]; let existing=null,newUrl=null; const begun=performance.now(), elapsed=()=>((performance.now()-begun)/1000);
async function desktop(){let s=page.viewportSize();if(!s||s.width!==1920||s.height!==1080){await page.setViewportSize({width:1920,height:1080});await page.waitForTimeout(200)}}
async function healthy(a){let t=await page.title().catch(()=>""),b=await page.locator("body").innerText().catch(()=>"");if(/\b500\b/.test(t)||/internal server error/i.test(b.slice(0,1000)))throw new Error(`[500] ${a} ${page.url()}`)}
async function go(r,a=r){
  const requestedUrl=new URL(r,base).toString();
  const x=await page.goto(requestedUrl,{waitUntil:"domcontentloaded"});

  if(x&&x.status()>=400){
    throw new Error(
      `[HTTP ${x.status()}] action=${a}; requested=${requestedUrl}; ` +
      `response=${x.url()}`
    );
  }

  await page.waitForTimeout(600);

  const body=await page.locator("body").innerText().catch(()=>"");
  const title=await page.title().catch(()=>"");

  if(
    /\b404\b/.test(title)
    || /page not found/i.test(body.slice(0,1200))
    || /this page wandered off/i.test(body.slice(0,1200))
  ){
    throw new Error(
      `[VISUAL 404] action=${a}; url=${page.url()}; title=${title}`
    );
  }

  await healthy(a);
}
async function login(){await go("/login");await page.locator('input[name="email"]').fill(email);await page.locator('input[name="password"]').fill(password);await Promise.all([page.waitForURL(u=>u.pathname.startsWith("/admin"),{timeout:30000}),page.locator('button[type="submit"]').click()]);await page.waitForTimeout(600)}
async function target(sel){for(const s of sel.split(",").map(x=>x.trim())){let l=page.locator(`${s}:visible`).first();if(await l.count()){await l.scrollIntoViewIfNeeded({timeout:2500});await page.waitForTimeout(200);return}}throw new Error(`No visible target: ${sel}`)}
async function discover(){await go("/admin/events");let adds=page.getByRole("link",{name:/add|new|create/i}).filter({visible:true});if(await adds.count()){let h=await adds.first().getAttribute("href");if(h)newUrl=new URL(h,base).pathname+new URL(h,base).search}
let edits=page.getByRole("link",{name:/edit/i}).filter({visible:true});if(await edits.count()){let h=await edits.first().getAttribute("href");if(h)existing=new URL(h,base).pathname+new URL(h,base).search}
if(!newUrl)throw new Error("No Add/New Event link found on /admin/events."); if(!existing)throw new Error("No existing Event edit link found on /admin/events."); console.log(`[VIDEO 04] New event route: ${newUrl}`);console.log(`[VIDEO 04] Existing event route: ${existing}`);await go(existing)}
async function title(){await page.setContent(`<!doctype html><body style="margin:0;background:white;width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;font-family:Arial"><div><img src="${logoData}" style="max-width:900px;max-height:390px"><div style="font-size:50px;font-weight:700">artsfol.io</div><div style="font-size:28px;margin-top:40px;font-weight:700">TRAINING VIDEO 04</div><div style="font-size:60px;font-weight:800">Events and Public History</div></div></body>`);await page.waitForTimeout(7000)}
async function end(){await page.setContent(`<!doctype html><body style="margin:0;background:white;width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;font-family:Arial"><div><img src="${logoData}" style="max-width:900px;max-height:410px"><div style="font-size:50px;font-weight:700">artsfol.io</div></div></body>`);await page.waitForTimeout(6000)}
async function act(a){if(a!=="public_mobile")await desktop();switch(a){
case"events_page":await go("/admin/events");break;case"events_list":await go("/admin/events");await target("table,.tenant-admin-main,[class*=event]");break;case"events_order":await go("/admin/events");await target("table,[class*=event],.tenant-admin-main");break;case"events_status":await go("/admin/events");await target('select[name*=status],input[name*=status],[class*=status],table,.tenant-admin-main');break;
case"new_event":await go(newUrl);break;case"new_date_text":await go(newUrl);await target('input[name="exhibition_date"],textarea[name="exhibition_date"],input[name*=date]');break;case"new_dates":await go(newUrl);await target('input[name="exhibition_date"],textarea[name="exhibition_date"],input[name*=date]');break;case"new_location":await go(newUrl);await target('input[name*=venue],input[name*=location],input[name*=city]');break;case"new_type_work":await go(newUrl);await target('input[name="exhibition_type"],select[name="exhibition_type"],input[name="work_name"]');break;case"new_description":await go(newUrl);await target('textarea[name*=description],textarea');break;case"new_save":await go(newUrl);await target('button[type="submit"]');break;
case"edit_event":await go(existing);break;case"edit_title":await go(existing);await target('input[name*=title],input[name*=name]');break;case"edit_date_text":await go(existing);await target('input[name="exhibition_date"],textarea[name="exhibition_date"],input[name*=date]');break;case"edit_location":await go(existing);await target('input[name*=venue],input[name*=location],input[name*=city]');break;case"edit_type_work":await go(existing);await target('input[name="exhibition_type"],select[name="exhibition_type"],input[name="work_name"]');break;case"edit_description":await go(existing);await target('textarea[name*=description],textarea');break;case"edit_save":await go(existing);await target('button[type="submit"]');break;
default:throw new Error(`Unknown action ${a}`)}}
try{if(!preflight)await title();await login();await discover();let n=0;for(const s of scenes){for(const c of s.cues){n++;let k=`${String(n).padStart(3,"0")}-${s.id}-${c.id}`;try{await act(c.action);if(preflight)console.log(`[PREFLIGHT VISITED] ${k} -> ${c.action}`);else{starts[k]=elapsed();await page.waitForTimeout(Math.max(1200,Math.round(Number(durations[k]||1.2)*1000)))}}catch(e){if(!preflight)throw e;issues.push({k,a:c.action,d:e.message,u:page.url()});console.error(`[PREFLIGHT ISSUE] ${k}: ${e.message}`)}}}
if(preflight){console.log("\n========== VIDEO 04 PREFLIGHT REPORT ==========");if(issues.length){console.error(`[FAIL] ${issues.length} issue(s)`);issues.forEach((x,i)=>console.error(`${i+1}. ${x.k} -> ${x.a}\n   ${x.d}\n   ${x.u}`));throw new Error(`Video 04 preflight found ${issues.length} issue(s).`)}console.log("[PASS] Every route and required control was found.\n================================================")}else{await end();fs.writeFileSync(path.join(wr,"cue-starts.json"),JSON.stringify(starts,null,2)+"\n")}}finally{await context.close();await browser.close()}
if(!preflight){fs.copyFileSync(await video.path(),path.join(rr,"video04-browser.webm"));console.log("[PASS] Video 04 browser recording complete.")}