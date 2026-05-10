<?php use App\Core\View; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($settings['site_title'] ?? $tenant['display_name']) ?></title>
  <meta name="description" content="<?= View::e($settings['seo_description'] ?? 'Artist portfolio') ?>">
  <link rel="stylesheet" href="/assets/site.css">
</head>
<body style="--primary: <?= View::e($settings['primary_color'] ?? '#111') ?>; --accent: <?= View::e($settings['accent_color'] ?? '#c9a85f') ?>; --bg: <?= View::e($settings['background_color'] ?? '#f7f2e8') ?>;">
<header class="site-header">
  <a class="brand" href="/"><?= View::e($settings['site_title'] ?? $tenant['display_name']) ?></a>
  <nav>
    <a href="/"><?= View::e($settings['home_tab'] ?? 'Home') ?></a>
    <a href="/portfolio"><?= View::e($settings['portfolio_tab'] ?? 'Portfolio') ?></a>
    <a href="/about"><?= View::e($settings['about_tab'] ?? 'About') ?></a>
    <a href="/contact"><?= View::e($settings['contact_tab'] ?? 'Contact') ?></a>
  </nav>
</header>
<main class="site-main">
  <?= $content ?>
</main>
<footer class="site-footer">© <?= View::e($settings['copyright_year'] ?? date('Y')) ?> <?= View::e($settings['site_title'] ?? $tenant['display_name']) ?></footer>
</body>
</html>
<?php // End of file. ?>
