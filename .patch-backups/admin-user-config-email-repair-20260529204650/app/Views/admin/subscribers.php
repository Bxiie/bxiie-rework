<?php ob_start(); use App\Core\View; ?>
<h2>Email subscribers</h2>
<p class="actions"><a class="button" href="/admin/subscribers/export">Export CSV</a></p>
<table>
  <tr><th>Email</th><th>Name</th><th>Source</th><th>Collected</th></tr>
  <?php foreach ($subscribers as $subscriber): ?>
    <tr>
      <td><?= View::e($subscriber['email']) ?></td>
      <td><?= View::e($subscriber['name'] ?? '') ?></td>
      <td><?= View::e($subscriber['source'] ?? '') ?></td>
      <td><?= View::e($subscriber['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
