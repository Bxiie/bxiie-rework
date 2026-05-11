<?php ob_start(); use App\Core\View; ?>
<h2>Edit portfolio section</h2>
<p><a href="/admin/portfolio">← Back to portfolio sections</a></p>
<form method="post" class="panel grid2">
  <input type="hidden" name="id" value="<?= (int) $section['id'] ?>">
  <label>Name<input name="name" value="<?= View::e($section['name']) ?>" required></label>
  <label>Slug<input name="slug" value="<?= View::e($section['slug']) ?>" required></label>
  <label>Sort order<input name="sort_order" type="number" value="<?= (int) $section['sort_order'] ?>"></label>
  <label class="wide">Description<textarea name="description"><?= View::e($section['description'] ?? '') ?></textarea></label>
  <button>Save section</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
