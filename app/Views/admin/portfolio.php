<?php ob_start(); use App\Core\View; ?>
<h2>Portfolio sections</h2><form method="post" class="panel"><label>Name<input name="name" required></label><label>Description<textarea name="description"></textarea></label><label>Sort order<input name="sort_order" type="number" value="100"></label><button>Add section</button></form><table><tr><th>Name</th><th>Slug</th><th>Sort</th></tr><?php foreach ($sections as $section): ?><tr><td><?= View::e($section['name']) ?></td><td><?= View::e($section['slug']) ?></td><td><?= (int) $section['sort_order'] ?></td></tr><?php endforeach; ?></table>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
