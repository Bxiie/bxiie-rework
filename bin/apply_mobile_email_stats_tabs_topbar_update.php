<?php
/**
 * Apply mobile/email/stats/tabs/topbar patch to the local workstation repo.
 */
declare(strict_types=1);
function fail(string $m): never { fwrite(STDERR, "ERROR: $m\n"); exit(1); }
function say(string $m): void { fwrite(STDOUT, "[bxiie-update] $m\n"); }
function mustRead(string $p): string { if (!is_file($p)) fail("Missing $p"); $c=file_get_contents($p); if($c===false) fail("Cannot read $p"); return $c; }
function mustWrite(string $p,string $c): void { if(file_put_contents($p,$c)===false) fail("Cannot write $p"); }
$root=getcwd();
foreach(['app/Views/public/layout.php','app/Views/admin/site.php','app/Controllers/AdminController.php','public/assets/site.css'] as $f){ if(!is_file("$root/$f")) fail("Run from repo root; missing $f"); }
copy(__DIR__.'/../public/assets/mobile.css', "$root/public/assets/mobile.css") || fail('Could not copy mobile.css');
copy(__DIR__.'/../scripts/migrate_mobile_email_stats_tabs_topbar.php', "$root/scripts/migrate_mobile_email_stats_tabs_topbar.php") || fail('Could not copy migration');
$layout=mustRead("$root/app/Views/public/layout.php");
if(!str_contains($layout,'name="viewport"')) $layout=str_replace('  <meta name="description"','  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n" . '  <meta name="description"',$layout);
if(!str_contains($layout,'/assets/mobile.css')) $layout=str_replace('  <link rel="stylesheet" href="/tenant.css">','  <link rel="stylesheet" href="/assets/mobile.css">' . "\n" . '  <link rel="stylesheet" href="/tenant.css">',$layout);
if(!str_contains($layout,'$topbarParts')){
  $layout=str_replace('<header class="site-header">', '<?php $topbarParts=[]; if(!empty($settings[\'topbar_background_color\'])){$topbarParts[]=\'background-color: \'.$settings[\'topbar_background_color\'];} $topbarStyle=implode(\'; \',$topbarParts); ?>' . "\n" . '<header class="site-header" style="<?= View::e($topbarStyle) ?>">',$layout);
}
mustWrite("$root/app/Views/public/layout.php",$layout);
$site=mustRead("$root/app/Views/admin/site.php");
if(!str_contains($site,'name="admin_email"')){
  $insert='<label>Admin notification email<input name="admin_email" type="email" value="<?= View::e($settings[\'admin_email\'] ?? \'\') ?>"></label>' . "\n" .
  '<label>Mail From address<input name="mail_from" type="email" value="<?= View::e($settings[\'mail_from\'] ?? \'noreply@bxiie.com\') ?>"></label>' . "\n" .
  '<label><input type="checkbox" name="notify_on_contact" value="1" <?= ($settings[\'notify_on_contact\'] ?? \'1\') === \'1\' ? \'checked\' : \'\' ?>> Email admin on contact message</label>' . "\n" .
  '<label><input type="checkbox" name="notify_on_subscriber" value="1" <?= ($settings[\'notify_on_subscriber\'] ?? \'1\') === \'1\' ? \'checked\' : \'\' ?>> Email admin on email signup</label>' . "\n" .
  '<label><input type="checkbox" name="portfolio_sections_as_tabs" value="1" <?= ($settings[\'portfolio_sections_as_tabs\'] ?? \'0\') === \'1\' ? \'checked\' : \'\' ?>> Show portfolio sections as tabs</label>' . "\n" .
  '<label>Top bar background color<input name="topbar_background_color" type="color" value="<?= View::e($settings[\'topbar_background_color\'] ?: \'#ffffff\') ?>"></label>' . "\n";
  $site=str_replace('<label>reCAPTCHA site key',$insert.'<label>reCAPTCHA site key',$site);
}
$site=str_replace('<option value="thumb" <?= ($settings[\'about_image_size\'] ?? \'\') === \'thumb\' ? \'selected\' : \'\' ?>>Thumbnail</option>','<option value="small" <?= ($settings[\'about_image_size\'] ?? \'\') === \'small\' ? \'selected\' : \'\' ?>>Small</option><option value="thumb" <?= ($settings[\'about_image_size\'] ?? \'\') === \'thumb\' ? \'selected\' : \'\' ?>>Thumbnail</option>',$site);
$site=str_replace('<option value="thumb" <?= ($settings[\'contact_image_size\'] ?? \'\') === \'thumb\' ? \'selected\' : \'\' ?>>Thumbnail</option>','<option value="small" <?= ($settings[\'contact_image_size\'] ?? \'\') === \'small\' ? \'selected\' : \'\' ?>>Small</option><option value="thumb" <?= ($settings[\'contact_image_size\'] ?? \'\') === \'thumb\' ? \'selected\' : \'\' ?>>Thumbnail</option>',$site);
mustWrite("$root/app/Views/admin/site.php",$site);
$admin=mustRead("$root/app/Controllers/AdminController.php");
foreach(['admin_email','mail_from','notify_on_contact','notify_on_subscriber','portfolio_sections_as_tabs','topbar_background_color','topbar_background_image_id','topbar_background_image_mode'] as $key){
  if(!str_contains($admin,"'".$key."'")) $admin=preg_replace("/('tenant_css',)/","$1\n                '$key',",$admin,1,$count);
}
mustWrite("$root/app/Controllers/AdminController.php",$admin);
say('Patch applied. Review git diff before commit.');
// End of file.
