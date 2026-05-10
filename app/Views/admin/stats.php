<?php ob_start(); use App\Core\View; ?>
<h2>Usage statistics</h2><form method="get" class="panel row"><label>From <input name="from" type="date" value="<?= View::e($from) ?>"></label><label>To <input name="to" type="date" value="<?= View::e($to) ?>"></label><button>Search</button></form><table><tr><th>Type</th><th>Path</th><th>Country</th><th>Hits</th></tr><?php foreach ($rows as $row): ?><tr><td><?= View::e($row['event_type']) ?></td><td><?= View::e($row['path']) ?></td><td><?= View::e($row['country_code']) ?></td><td><?= (int) $row['hits'] ?></td></tr><?php endforeach; ?></table>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
