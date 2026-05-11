<?php
use App\Core\View;

$browserTitle = $settings['browser_title'] ?? ($settings['site_title'] ?? $tenant['display_name']);
$brandTitle = $settings['site_title'] ?? $tenant['display_name'];
$copyrightName = $settings['copyright_name'] ?? $brandTitle;
$bgStyle = '';
if (!empty($backgroundImage['storage_key'])) {
    $bgUrl = '/media/' . View::e($backgroundImage['storage_key']) . '-large.jpg';
    $mode = $settings['background_mode'] ?? 'single';
    $tileSize = $settings['background_tile_size'] ?? '360px';
    $opacity = $settings['background_opacity'] ?? '0.12';
    $bgStyle = '--site-bg-image:url(' . $bgUrl . ');--site-bg-repeat:' . ($mode === 'tile' ? 'repeat' : 'no-repeat') . ';--site-bg-size:' . ($mode === 'tile' ? View::e($tileSize) : 'cover') . ';--site-bg-opacity:' . View::e($opacity) . ';';
}
$recaptchaSiteKey = trim((string) ($settings['recaptcha_site_key'] ?? ''));
$portfolioHref = $slugs['portfolio'] ?? '/portfolio';
$aboutHref = $slugs['about'] ?? '/about';
$contactHref = $slugs['contact'] ?? '/contact';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($browserTitle) ?></title>
  <meta name="description" content="<?= View::e($settings['seo_description'] ?? 'Artist portfolio') ?>">
  <link rel="stylesheet" href="/assets/site.css">
  <link rel="stylesheet" href="/tenant.css">
  <?php if ($recaptchaSiteKey !== ''): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
</head>
<body style="--primary: <?= View::e($settings['primary_color'] ?? '#111') ?>; --accent: <?= View::e($settings['accent_color'] ?? '#c9a85f') ?>; --bg: <?= View::e($settings['background_color'] ?? '#f7f2e8') ?>; <?= $bgStyle ?>">
<header class="site-header">
  <a class="brand" href="/"><?= View::e($brandTitle) ?></a>
  <nav>
    <a href="/"><?= View::e($settings['home_tab'] ?? 'Home') ?></a>
    <a href="<?= View::e($portfolioHref) ?>"><?= View::e($settings['portfolio_tab'] ?? 'Portfolio') ?></a>
    <a href="<?= View::e($aboutHref) ?>"><?= View::e($settings['about_tab'] ?? 'About') ?></a>
    <a href="<?= View::e($contactHref) ?>"><?= View::e($settings['contact_tab'] ?? 'Contact') ?></a>
  </nav>
</header>
<main class="site-main">
  <?= $content ?>
</main>
<footer class="site-footer">© <?= View::e($settings['copyright_year'] ?? date('Y')) ?> <?= View::e($copyrightName) ?></footer>


</body>
</html>
<?php // End of file. ?>
