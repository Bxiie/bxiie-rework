/* Browser serialization through PHP's actual default input limit. No network posting. */
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..');
const html=execFileSync('php',[path.join(__dirname,'social_post_requests.php'),'--html','--large-catalog'],{encoding:'utf8'});
function parseRequest(body) {
    return JSON.parse(execFileSync('php',['-d','max_input_vars=1000','-d','display_errors=stderr','-r',
        'parse_str(stream_get_contents(STDIN), $data); echo json_encode($data);'],{input:body,encoding:'utf8'}));
}
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage();
  await page.route('**/*',r=>r.fulfill({contentType:'application/json',body:'{}'}));
  await page.goto('http://artsfolio.test/admin/social/compose');
  await page.setContent(html.replace(/<script[^>]*>[\s\S]*?<\/script>/g,''));
  assert.equal(await page.locator('[data-social-media-card]').count(),500);
  const request=async()=>parseRequest(await page.locator('[data-social-compose]').evaluate(f=>new URLSearchParams(new FormData(f)).toString()));
  // Server-rendered form must be safe even before JavaScript runs.
  let data=await request();
  assert.equal(data.publish_action,'post_now','PHP discarded publishing mode for a large catalog');
  assert.equal(Object.keys(data.media_order).length,1,'Unselected media settings were submitted');
  await page.addScriptTag({content:fs.readFileSync(path.join(root,'public/assets/social-publishing.js'),'utf8')});
  await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  await page.waitForFunction(()=>document.querySelector('[data-social-submit]').textContent==='Post Now');
  await page.locator('details').evaluate(e=>e.open=true);
  // Select images near the end, well beyond the old truncation boundary.
  for(let id=492;id<=500;id++) await page.locator(`input[name="media_artwork_ids[]"][value="${id}"]`).check();
  data=await request();
  assert.equal(data.publish_action,'post_now');
  assert.equal(data.media_artwork_ids.length,10);
  assert.equal(Object.keys(data.media_order).length,10);
  assert('500' in data.crop_mode);
  await page.selectOption('select[name="crop_mode[500]"]','portrait');
  await page.selectOption('[name=publish_action]','schedule');
  await page.fill('[name=scheduled_local]','2099-07-01T12:00');
  data=await request();
  assert.equal(data.publish_action,'schedule');
  assert.equal(data.scheduled_local,'2099-07-01T12:00');
  assert.equal(data.crop_mode['500'],'portrait');
  await page.locator('input[name="media_artwork_ids[]"][value="500"]').uncheck();
  data=await request();
  assert.equal(data.media_artwork_ids.length,9);
  assert(!('500' in data.crop_mode));
  await page.locator('input[name="media_artwork_ids[]"][value="500"]').check();
  data=await request();
  assert.equal(data.crop_mode['500'],'portrait','Reselection lost the crop choice');
  console.log('PASS: 500-artwork form preserves immediate/scheduled modes and all ten selected images through PHP max_input_vars=1000, with or without JavaScript.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
