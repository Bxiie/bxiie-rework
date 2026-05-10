<?php ob_start(); use App\Core\View; ?>
<section class="hero">
  <h1><?= View::e($settings['site_title'] ?? $tenant['display_name']) ?></h1>
  <p><?= View::e($settings['home_intro'] ?? 'Contemporary mixed-media work, archival textures, fragments, signals, and beautiful static from the machine room of memory.') ?></p>
</section>
<section class="grid">
  <?php foreach ($images as $image): ?>
    <a class="card" href="/image/<?= (int) $image['id'] ?>"><img src="/media/<?= View::e($image['storage_key']) ?>-medium.jpg" alt="<?= View::e($image['alt_text'] ?: $image['title']) ?>"><span><?= View::e($image['title']) ?></span></a>
  <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
