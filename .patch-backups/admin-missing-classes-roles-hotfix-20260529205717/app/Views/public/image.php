<?php ob_start(); use App\Core\View; ?>
<article class="artwork">
  <img src="/media/<?= View::e($image['storage_key']) ?>-large.jpg" alt="<?= View::e($image['alt_text'] ?: $image['title']) ?>">
  <h1><?= View::e($image['title']) ?></h1>
  <p class="art-meta"><?php if (!empty($image['year'])): ?><?= View::e($image['year']) ?><?php endif; ?><?php if (!empty($image['year']) && !empty($image['medium'])): ?> · <?php endif; ?><?php if (!empty($image['medium'])): ?><?= View::e($image['medium']) ?><?php endif; ?></p>
  <div class="prose"><?= View::html($image['description'] ?? '') ?></div>
</article>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
