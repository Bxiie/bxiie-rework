<?php ob_start(); use App\Core\View; ?>
<h2>Images</h2>
<form method="post" enctype="multipart/form-data" class="panel grid2">
  <label>Title<input name="title" required></label>
  <label>Year<input name="year"></label>
  <label>Medium<input name="medium"></label>
  <label>Dimensions<input name="dimensions"></label>
  <label>Price<input name="price"></label>
  <label>Sale status<input name="sale_status"></label>
  <label>Location<input name="location"></label>
  <label>Tags<input name="tags"></label>
  <label class="wide">Description / HTML allowed<textarea name="description"></textarea></label>
  <label class="wide">Alt text<input name="alt_text"></label>
  <label>Sort order<input name="sort_order" type="number" value="100"></label>
  <label>Image<input type="file" name="image" accept="image/*" required></label>
  <label><input type="checkbox" name="watermark" checked> Apply watermark to derivatives</label>
  <label><input type="checkbox" name="featured_home"> Feature on home</label>
  <label><input type="checkbox" name="featured_rotator"> Use in rotator</label>
  <label><input type="checkbox" name="featured_about"> Candidate for about image</label>
  <label><input type="checkbox" name="featured_contact"> Candidate for contact image</label>
  <label><input type="checkbox" name="background_image"> Candidate for background</label>
  <label><input type="checkbox" name="is_draft"> Save as draft/private</label>
  <button>Upload</button>
</form>
<table>
  <tr><th>Preview</th><th>Title</th><th>Year</th><th>Medium</th><th>Status</th><th>Featured</th><th>Action</th></tr>
  <?php foreach ($images as $image): ?>
    <tr>
      <td><?php if ($image['storage_key']): ?><img class="thumb" src="/media/<?= View::e($image['storage_key']) ?>-thumb.jpg" alt=""><?php endif; ?></td>
      <td><?= View::e($image['title']) ?></td>
      <td><?= View::e($image['year'] ?? '') ?></td>
      <td><?= View::e($image['medium'] ?? '') ?></td>
      <td><?= $image['is_public'] ? 'Public' : 'Draft' ?></td>
      <td><?= $image['featured_home'] ? 'Home ' : '' ?><?= $image['featured_rotator'] ? 'Rotator ' : '' ?><?= $image['featured_about'] ? 'About ' : '' ?><?= $image['featured_contact'] ? 'Contact ' : '' ?><?= $image['background_image'] ? 'Background' : '' ?></td>
      <td><a href="/admin/images/edit?id=<?= (int) $image['id'] ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
