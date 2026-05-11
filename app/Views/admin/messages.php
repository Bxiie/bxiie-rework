<?php ob_start(); use App\Core\View; ?>
<h2>Contact messages</h2>
<p class="notice">Contact messages submitted from the public contact form appear here.</p>
<table>
  <tr><th>Received</th><th>Name</th><th>Email</th><th>Message</th></tr>
  <?php foreach ($messages as $message): ?>
    <tr>
      <td><?= View::e($message['created_at']) ?></td>
      <td><?= View::e($message['name'] ?? '') ?></td>
      <td><a href="mailto:<?= View::e($message['email'] ?? '') ?>"><?= View::e($message['email'] ?? '') ?></a></td>
      <td><?= nl2br(View::e($message['message'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
