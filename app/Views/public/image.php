<?php ob_start(); use App\Core\View; ?>
<article class="artwork">
  <img src="/media/<?= View::e($image['storage_key']) ?>-large.jpg" alt="<?= View::e($image['alt_text'] ?: $image['title']) ?>">
  <h1><?= View::e($image['title']) ?></h1>
  <p><?= nl2br(View::e($image['description'])) ?></p>
</article>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
