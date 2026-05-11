<?php ob_start(); use App\Core\View; ?>
<h1>About</h1>
<?php if (!empty($aboutImage['storage_key'])): ?>
  <img class="page-image page-image-<?= View::e($aboutImageSize ?? 'medium') ?>" src="/media/<?= View::e($aboutImage['storage_key']) ?>-<?= View::e($aboutImageSize ?? 'medium') ?>.jpg" alt="<?= View::e($aboutImage['alt_text'] ?: $aboutImage['title']) ?>">
<?php endif; ?>
<article class="prose"><?= View::html($settings['about_content'] ?? '') ?></article>
<?php if (!empty($events)): ?>
<section class="events">
  <h2>Recent exhibitions</h2>
  <?php foreach ($events as $event): ?>
    <article class="event-row">
      <div class="event-date"><?= View::e($event['display_date'] ?: $event['event_date']) ?></div>
      <div>
        <h3><?= View::e($event['title']) ?></h3>
        <p><strong><?= View::e($event['event_type'] ?? '') ?></strong></p>
        <p><?= View::e(trim(($event['city'] ?? '') . ', ' . ($event['state'] ?? ''), ', ')) ?></p>
        <?php if (!empty($event['work_name'])): ?><p><?= View::e($event['work_name']) ?></p><?php endif; ?>
        <?php if (!empty($event['additional_info'])): ?><div class="prose small"><?= View::html($event['additional_info']) ?></div><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
<?php // End of file. ?>
