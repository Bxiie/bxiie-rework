<?php ob_start(); use App\Core\View; ?>
<h1>Contact</h1>
<?php if (!empty($_GET['subscribed'])): ?><p class="notice">Thanks. You have been added to the email list.</p><?php endif; ?>
<?php if (!empty($_GET['subscribe_error'])): ?><p class="error">Email signup failed. Please try again.</p><?php endif; ?>
<?php if ($message): ?><p class="notice"><?= View::e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= View::e($error) ?></p><?php endif; ?>
<?php if (!empty($contactImage['storage_key'])): ?>
  <img class="page-image page-image-<?= View::e($contactImageSize ?? 'medium') ?>" src="/media/<?= View::e($contactImage['storage_key']) ?>-<?= View::e($contactImageSize ?? 'medium') ?>.jpg" alt="<?= View::e($contactImage['alt_text'] ?: $contactImage['title']) ?>">
<?php endif; ?>
<article class="prose"><?= View::html($settings['contact_details'] ?? '') ?></article>
<form method="post" class="form">
  <label>Name <input name="name" required></label>
  <label>Email <input name="email" type="email" required></label>
  <label>Message <textarea name="message" required></textarea></label>
  <?php if (!empty($settings['recaptcha_site_key'])): ?><div class="g-recaptcha" data-sitekey="<?= View::e($settings['recaptcha_site_key']) ?>"></div><?php endif; ?>
  <button>Send</button>
</form>
<form method="post" action="/subscribe" class="form compact">
  <h2>Email list</h2>
  <label>Name <input name="name"></label>
  <label>Email <input name="email" type="email" required></label>
  <input type="hidden" name="source" value="contact">
  <?php if (!empty($settings['recaptcha_site_key'])): ?><div class="g-recaptcha" data-sitekey="<?= View::e($settings['recaptcha_site_key']) ?>"></div><?php endif; ?>
  <button>Subscribe</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
