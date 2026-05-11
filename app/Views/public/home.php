<?php ob_start(); use App\Core\View; ?>
<section class="hero">
  <h1><?= View::e($settings['artist_name'] ?? ($settings['site_title'] ?? $tenant['display_name'])) ?></h1>
  <div class="prose"><?= View::html($settings['home_intro'] ?? 'Contemporary mixed-media work, archival textures, fragments, signals, and beautiful static from the machine room of memory.') ?></div>
</section>
<section class="grid">
  <?php foreach ($images as $image): ?>
    <a class="card" href="/image/<?= (int) $image['id'] ?>">
      <img src="/media/<?= View::e($image['storage_key']) ?>-medium.jpg" alt="<?= View::e($image['alt_text'] ?: $image['title']) ?>">
      <span><?= View::e($image['title']) ?></span>
      <?php if (!empty($image['year'])): ?><small><?= View::e($image['year']) ?></small><?php endif; ?>
    </a>
  <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
