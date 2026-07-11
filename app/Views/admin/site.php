<?php ob_start(); use App\Core\View; ?>
<h2>Site look, labels, routes, and defaults</h2>
<form method="post" class="panel grid2">
  <label>Site title / visible logo text<input name="site_title" value="<?= View::e($settings['site_title'] ?? '') ?>"></label>
  <label>Browser tab title<input name="browser_title" value="<?= View::e($settings['browser_title'] ?? ($settings['site_title'] ?? '')) ?>"></label>
  <label>Artist name<input name="artist_name" value="<?= View::e($settings['artist_name'] ?? ($settings['site_title'] ?? '')) ?>"></label>
  <label>Copyright name<input name="copyright_name" value="<?= View::e($settings['copyright_name'] ?? ($settings['site_title'] ?? '')) ?>"></label>

  <label>Home tab<input name="home_tab" value="<?= View::e($settings['home_tab'] ?? 'Home') ?>"></label>
  <label>Portfolio tab<input name="portfolio_tab" value="<?= View::e($settings['portfolio_tab'] ?? 'Portfolio') ?>"></label>
  <label>About tab<input name="about_tab" value="<?= View::e($settings['about_tab'] ?? 'About') ?>"></label>
  <label>Contact tab<input name="contact_tab" value="<?= View::e($settings['contact_tab'] ?? 'Contact') ?>"></label>

  <label>Portfolio slug<input name="portfolio_slug" value="<?= View::e($settings['portfolio_slug'] ?? 'portfolio') ?>"></label>
  <label>About slug<input name="about_slug" value="<?= View::e($settings['about_slug'] ?? 'about') ?>"></label>
  <label>Contact slug<input name="contact_slug" value="<?= View::e($settings['contact_slug'] ?? 'contact') ?>"></label>
  <p class="wide muted">The home page remains available at <code>/</code>. Slugs control public URLs such as <code>/portfolio</code>, <code>/about</code>, and <code>/contact</code>.</p>

  <label>Primary color<input name="primary_color" type="color" value="<?= View::e($settings['primary_color'] ?? '#111111') ?>"></label>
  <label>Accent color<input name="accent_color" type="color" value="<?= View::e($settings['accent_color'] ?? '#c9a85f') ?>"></label>
  <label>Background color<input name="background_color" type="color" value="<?= View::e($settings['background_color'] ?? '#f7f2e8') ?>"></label>

  <label class="wide">Home intro text / HTML allowed<textarea name="home_intro"><?= View::e($settings['home_intro'] ?? '') ?></textarea></label>

  <label>Recent exhibitions heading<input name="exhibitions_heading" value="<?= View::e($settings['exhibitions_heading'] ?? 'Recent exhibitions') ?>"></label>
  <label>Exhibitions display<select name="exhibitions_display_mode"><option value="text" <?= ($settings['exhibitions_display_mode'] ?? 'text') === 'text' ? 'selected' : '' ?>>Text / stacked</option><option value="table" <?= ($settings['exhibitions_display_mode'] ?? '') === 'table' ? 'selected' : '' ?>>Table</option></select></label>

  <label>About page image<select name="about_image_id"><option value="">None</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string)($settings['about_image_id'] ?? '') === (string)$image['id'] ? 'selected' : '' ?>><?= View::e($image['title']) ?><?= $image['year'] ? ' · ' . View::e($image['year']) : '' ?></option><?php endforeach; ?></select></label>
  <label>About image size<select name="about_image_size"><option value="thumb" <?= ($settings['about_image_size'] ?? '') === 'thumb' ? 'selected' : '' ?>>Thumbnail</option><option value="medium" <?= ($settings['about_image_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option><option value="large" <?= ($settings['about_image_size'] ?? '') === 'large' ? 'selected' : '' ?>>Large</option></select></label>

  <label>Contact page image<select name="contact_image_id"><option value="">None</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string)($settings['contact_image_id'] ?? '') === (string)$image['id'] ? 'selected' : '' ?>><?= View::e($image['title']) ?><?= $image['year'] ? ' · ' . View::e($image['year']) : '' ?></option><?php endforeach; ?></select></label>
  <label>Contact image size<select name="contact_image_size"><option value="thumb" <?= ($settings['contact_image_size'] ?? '') === 'thumb' ? 'selected' : '' ?>>Thumbnail</option><option value="medium" <?= ($settings['contact_image_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option><option value="large" <?= ($settings['contact_image_size'] ?? '') === 'large' ? 'selected' : '' ?>>Large</option></select></label>

  <label>Background image<select name="background_image_id"><option value="">None</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string)($settings['background_image_id'] ?? '') === (string)$image['id'] ? 'selected' : '' ?>><?= View::e($image['title']) ?><?= $image['year'] ? ' · ' . View::e($image['year']) : '' ?></option><?php endforeach; ?></select></label>
  <label>Background mode<select name="background_mode"><option value="single" <?= ($settings['background_mode'] ?? 'single') === 'single' ? 'selected' : '' ?>>Single image</option><option value="tile" <?= ($settings['background_mode'] ?? '') === 'tile' ? 'selected' : '' ?>>Tiled</option></select></label>
  <label>Tile size CSS value<input name="background_tile_size" value="<?= View::e($settings['background_tile_size'] ?? '360px') ?>"></label>
  <label>Background opacity 0-1<input name="background_opacity" value="<?= View::e($settings['background_opacity'] ?? '0.12') ?>"></label>

  <label>Cloudflare Turnstile site key<input name="turnstile_site_key" value="<?= View::e($settings['turnstile_site_key'] ?? '') ?>"></label>
  <label>Cloudflare Turnstile secret key<input name="turnstile_secret_key" value="<?= View::e($settings['turnstile_secret_key'] ?? '') ?>"></label>

  <label class="wide">Tenant-specific CSS<textarea name="tenant_css" class="code-area" spellcheck="false"><?= View::e($settings['tenant_css'] ?? '') ?></textarea></label>

  <button>Save site settings</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
