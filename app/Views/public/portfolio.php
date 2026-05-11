<?php ob_start(); use App\Core\View; ?>
<h1>Portfolio</h1>
<div class="chips">
  <?php foreach ($sections as $section): ?><a href="/portfolio/<?= View::e($section['slug']) ?>"><?= View::e($section['name']) ?></a><?php endforeach; ?>
</div>
<section class="grid">
  <?php foreach ($images as $image): ?>
    <a class="card" href="/image/<?= (int) $image['id'] ?>">
      <img src="/media/<?= View::e($image['storage_key']) ?>-thumb.jpg" alt="<?= View::e($image['alt_text'] ?: $image['title']) ?>">
      <span><?= View::e($image['title']) ?></span>
      <?php if (!empty($image['year'])): ?><small><?= View::e($image['year']) ?></small><?php endif; ?>
    </a>
  <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
