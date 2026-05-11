<?php ob_start(); use App\Core\View; ?>
<h1>About</h1>
<article class="prose"><?= nl2br(View::e($settings['about_content'] ?? '')) ?></article>

<?php if (!empty($events)): ?>
<section class="events-list">
  <h2>Exhibitions and events</h2>
  <table>
    <tr>
      <th>Date</th>
      <th>Exhibition</th>
      <th>Type</th>
      <th>Location</th>
      <th>Work</th>
      <th>Additional information</th>
    </tr>
    <?php foreach ($events as $event): ?>
      <tr>
        <td><?= View::e($event['display_date'] ?? $event['event_date'] ?? '') ?></td>
        <td><?= View::e($event['title'] ?? '') ?></td>
        <td><?= View::e($event['event_type'] ?? $event['description'] ?? '') ?></td>
        <td><?= View::e(trim(($event['city'] ?? '') . ', ' . ($event['state'] ?? ''), ', ')) ?></td>
        <td><?= View::e($event['work_name'] ?? '') ?></td>
        <td><?= strip_tags((string) ($event['additional_info'] ?? ''), '<a><b><strong><em><i>') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
