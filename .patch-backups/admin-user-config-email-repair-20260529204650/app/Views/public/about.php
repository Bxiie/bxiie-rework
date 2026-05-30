<?php ob_start(); use App\Core\View; ?>
<h1>About</h1>
<?php if (!empty($aboutImage['storage_key'])): ?>
  <img class="page-image page-image-<?= View::e($aboutImageSize ?? 'medium') ?>" src="/media/<?= View::e($aboutImage['storage_key']) ?>-<?= View::e($aboutImageSize ?? 'medium') ?>.jpg" alt="<?= View::e($aboutImage['alt_text'] ?: $aboutImage['title']) ?>">
<?php endif; ?>
<article class="prose"><?= View::html($settings['about_content'] ?? '') ?></article>
<?php if (!empty($events)): ?>
<section class="events">
  <h2><?= View::e($settings['exhibitions_heading'] ?? 'Recent exhibitions') ?></h2>
  <?php if (($settings['exhibitions_display_mode'] ?? 'text') === 'table'): ?>
    <table class="events-table">
      <tr><th>Date</th><th>Exhibition</th><th>Type</th><th>Location</th><th>Work</th><th>Additional information</th></tr>
      <?php foreach ($events as $event): ?>
        <tr>
          <td><?= View::e($event['display_date'] ?: $event['event_date']) ?></td>
          <td><?= View::e($event['title']) ?></td>
          <td><?= View::e($event['event_type'] ?? '') ?></td>
          <td><?= View::e(trim(($event['city'] ?? '') . ', ' . ($event['state'] ?? ''), ', ')) ?></td>
          <td><?= View::e($event['work_name'] ?? '') ?></td>
          <td><div class="prose small"><?= View::html($event['additional_info'] ?? '') ?></div></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <?php foreach ($events as $event): ?>
      <article class="event-row">
        <div class="event-date"><?= View::e($event['display_date'] ?: $event['event_date']) ?></div>
        <div>
          <h3><?= View::e($event['title']) ?></h3>
          <?php if (!empty($event['event_type'])): ?><p><strong><?= View::e($event['event_type']) ?></strong></p><?php endif; ?>
          <p><?= View::e(trim(($event['city'] ?? '') . ', ' . ($event['state'] ?? ''), ', ')) ?></p>
          <?php if (!empty($event['work_name'])): ?><p><?= View::e($event['work_name']) ?></p><?php endif; ?>
          <?php if (!empty($event['additional_info'])): ?><div class="prose small"><?= View::html($event['additional_info']) ?></div><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
