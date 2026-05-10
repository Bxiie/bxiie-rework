<?php ob_start(); use App\Core\View; ?>
<h1>Contact</h1>
<?php if ($message): ?><p class="notice"><?= View::e($message) ?></p><?php endif; ?>
<article class="prose"><?= nl2br(View::e($settings['contact_details'] ?? '')) ?></article>
<form method="post" class="form">
  <label>Name <input name="name" required></label>
  <label>Email <input name="email" type="email" required></label>
  <label>Message <textarea name="message" required></textarea></label>
  <button>Send</button>
</form>
<form method="post" action="/subscribe" class="form compact">
  <h2>Newsletter</h2>
  <label>Name <input name="name"></label>
  <label>Email <input name="email" type="email" required></label>
  <button>Subscribe</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
