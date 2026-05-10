<?php ob_start(); ?>
<h2>Dashboard</h2><div class="cards"><?php foreach ($counts as $label => $count): ?><div class="card"><strong><?= (int) $count ?></strong><span><?= htmlspecialchars($label) ?></span></div><?php endforeach; ?></div>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
