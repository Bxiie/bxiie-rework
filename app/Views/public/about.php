<?php ob_start(); use App\Core\View; ?>
<h1>About</h1>
<article class="prose"><?= nl2br(View::e($settings['about_content'] ?? '')) ?></article>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
