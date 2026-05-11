<?php ob_start(); use App\Core\View; ?>
<h2>Edit image</h2>
<p><a href="/admin/images">← Back to images</a></p>
<form method="post" class="panel grid2">
  <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
  <label>Title<input name="title" value="<?= View::e($image['title']) ?>" required></label>
  <label>Year<input name="year" value="<?= View::e($image['year'] ?? '') ?>"></label>
  <label>Medium<input name="medium" value="<?= View::e($image['medium'] ?? '') ?>"></label>
  <label>Dimensions<input name="dimensions" value="<?= View::e($image['dimensions'] ?? '') ?>"></label>
  <label>Price<input name="price" value="<?= View::e($image['price'] ?? '') ?>"></label>
  <label>Sale status<input name="sale_status" value="<?= View::e($image['sale_status'] ?? '') ?>"></label>
  <label>Location<input name="location" value="<?= View::e($image['location'] ?? '') ?>"></label>
  <label>Tags<input name="tags" value="<?= View::e($image['tags'] ?? '') ?>"></label>
  <label>Sort order<input name="sort_order" type="number" value="<?= (int) $image['sort_order'] ?>"></label>
  <label class="wide">Description / HTML allowed<textarea name="description"><?= View::e($image['description'] ?? '') ?></textarea></label>
  <label class="wide">Alt text<input name="alt_text" value="<?= View::e($image['alt_text'] ?? '') ?>"></label>
  <label><input type="checkbox" name="is_draft" <?= $image['is_draft'] ? 'checked' : '' ?>> Draft/private</label>
  <label><input type="checkbox" name="featured_home" <?= $image['featured_home'] ? 'checked' : '' ?>> Feature on home</label>
  <label><input type="checkbox" name="featured_rotator" <?= $image['featured_rotator'] ? 'checked' : '' ?>> Use in rotator</label>
  <label><input type="checkbox" name="featured_about" <?= $image['featured_about'] ? 'checked' : '' ?>> Candidate for about image</label>
  <label><input type="checkbox" name="featured_contact" <?= $image['featured_contact'] ? 'checked' : '' ?>> Candidate for contact image</label>
  <label><input type="checkbox" name="background_image" <?= $image['background_image'] ? 'checked' : '' ?>> Candidate for background</label>
  <label><input type="checkbox" name="watermarked" <?= $image['watermarked'] ? 'checked' : '' ?>> Watermarked</label>
  <button>Save image details</button>
</form>
<?php if ($image['storage_key']): ?><img class="preview" src="/media/<?= View::e($image['storage_key']) ?>-medium.jpg" alt=""><?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
