<?php ob_start(); use App\Core\View; ?>
<h2>Usage statistics</h2>

<section class="panel">
  <p><strong>Stats start:</strong> <?= View::e($stats_started_at ?? 'Unknown') ?></p>
  <p class="muted">Resetting stats deletes tenant page-view/image-hit rows and starts a new reporting window. It does not delete images, subscribers, contact messages, or the IP location cache.</p>
  <form method="post" action="/admin/stats/reset" onsubmit="return confirm('Reset all usage statistics for this tenant? This cannot be undone.');" class="row">
    <label>Type RESET to confirm <input name="confirm" autocomplete="off"></label>
    <button class="danger">Reset stats to zero</button>
  </form>
</section>

<form method="get" class="panel row">
  <label>From <input name="from" type="date" value="<?= View::e($from) ?>"></label>
  <label>To <input name="to" type="date" value="<?= View::e($to) ?>"></label>
  <label>Search image/name/location <input name="q" value="<?= View::e($q ?? '') ?>"></label>
  <label>Group <select name="group"><option value="path" <?= ($group ?? 'path') === 'path' ? 'selected' : '' ?>>Path / image</option><option value="location" <?= ($group ?? '') === 'location' ? 'selected' : '' ?>>Location</option></select></label>
  <button>Search</button>
</form>
<?php if (($group ?? 'path') === 'location'): ?>
<table>
  <tr><th>City</th><th>State</th><th>Country</th><th>Country code</th><th>Hits</th></tr>
  <?php foreach ($rows as $row): ?>
    <tr><td><?= View::e($row['city'] ?? '') ?></td><td><?= View::e($row['state'] ?? '') ?></td><td><?= View::e($row['country'] ?? '') ?></td><td><?= View::e($row['country_code'] ?? '') ?></td><td><?= (int) $row['hits'] ?></td></tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
<table>
  <tr><th>Image</th><th>Type</th><th>Path</th><th>Name</th><th>Location</th><th>Hits</th></tr>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?php if (!empty($row['storage_key'])): ?><img class="thumb" src="/media/<?= View::e($row['storage_key']) ?>-thumb.jpg" alt=""><?php endif; ?></td>
      <td><?= View::e($row['event_type']) ?></td>
      <td><?= View::e($row['path']) ?></td>
      <td><?= View::e($row['image_title'] ?? '') ?></td>
      <td><?= View::e(trim(($row['city'] ?? '') . ', ' . ($row['state'] ?? '') . ', ' . ($row['country'] ?? ''), ', ')) ?></td>
      <td><?= (int) $row['hits'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
