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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($browserTitle) ?></title>
  <meta name="description" content="<?= View::e($settings['seo_description'] ?? 'Artist portfolio') ?>">
  <link rel="stylesheet" href="/assets/site.css">
</head>
<body style="--primary: <?= View::e($settings['primary_color'] ?? '#111') ?>; --accent: <?= View::e($settings['accent_color'] ?? '#c9a85f') ?>; --bg: <?= View::e($settings['background_color'] ?? '#f7f2e8') ?>; <?= $bgStyle ?>">
<header class="site-header">
  <a class="brand" href="/"><?= View::e($brandTitle) ?></a>
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
<footer class="site-footer">© <?= View::e($settings['copyright_year'] ?? date('Y')) ?> <?= View::e($copyrightName) ?></footer>
<div id="subscribe-modal" class="modal hidden" aria-hidden="true">
  <div class="modal-card">
    <button class="modal-close" type="button" aria-label="Close">×</button>
    <h2>Stay in the loop</h2>
    <p>Get occasional updates about new work, exhibitions, and studio news.</p>
    <form id="subscribe-modal-form" method="post" action="/subscribe">
      <label>Name <input name="name"></label>
      <label>Email <input name="email" type="email" required></label>
      <input type="hidden" name="source" value="modal">
      <button>Subscribe</button>
    </form>
  </div>
</div>
<script>
(function () {
  const subscribedKey = 'bxiie_subscribed';
  const dismissedKey = 'bxiie_subscribe_dismissed';
  const modal = document.getElementById('subscribe-modal');
  const form = document.getElementById('subscribe-modal-form');
  const close = modal ? modal.querySelector('.modal-close') : null;

  if (!modal || localStorage.getItem(subscribedKey) || localStorage.getItem(dismissedKey)) {
    return;
  }

  window.setTimeout(function () {
    if (!localStorage.getItem(subscribedKey) && !localStorage.getItem(dismissedKey)) {
      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
    }
  }, 60000);

  if (close) {
    close.addEventListener('click', function () {
      localStorage.setItem(dismissedKey, '1');
      modal.classList.add('hidden');
    });
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      fetch('/subscribe', {
        method: 'POST',
        headers: {'X-Requested-With': 'fetch', 'Accept': 'application/json'},
        body: new FormData(form)
      }).then(function () {
        localStorage.setItem(subscribedKey, '1');
        modal.classList.add('hidden');
      });
    });
  }
}());
</script>
</body>
</html>
<?php // End of file. ?>
