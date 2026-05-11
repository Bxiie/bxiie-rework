<?php ob_start(); use App\Core\View; ?>
<h2>Content and social URLs</h2>
<form method="post" class="panel">
  <label>About content / HTML allowed<textarea name="about_content"><?= View::e($settings['about_content'] ?? '') ?></textarea></label>
  <label>Contact details / HTML allowed<textarea name="contact_details"><?= View::e($settings['contact_details'] ?? '') ?></textarea></label>
  <label>Instagram URL<input name="instagram_url" value="<?= View::e($settings['instagram_url'] ?? '') ?>"></label>
  <label>Facebook URL<input name="facebook_url" value="<?= View::e($settings['facebook_url'] ?? '') ?>"></label>
  <label>LinkedIn URL<input name="linkedin_url" value="<?= View::e($settings['linkedin_url'] ?? '') ?>"></label>
  <button>Save content details</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
