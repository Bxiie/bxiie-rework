<?php ob_start(); use App\Core\View; ?>
<h2>Edit event</h2>
<p><a href="/admin/events">← Back to events</a></p>
<form method="post" class="panel grid2">
  <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
  <label>Exhibition name<input name="title" value="<?= View::e($event['title']) ?>" required></label>
  <label>Type<input name="event_type" value="<?= View::e($event['event_type'] ?? '') ?>"></label>
  <label>Date for sorting<input name="event_date" type="date" value="<?= View::e($event['event_date'] ?? '') ?>"></label>
  <label>Displayed date<input name="display_date" value="<?= View::e($event['display_date'] ?? '') ?>"></label>
  <label>Venue<input name="venue" value="<?= View::e($event['venue'] ?? '') ?>"></label>
  <label>City<input name="city" value="<?= View::e($event['city'] ?? '') ?>"></label>
  <label>State / region<input name="state" value="<?= View::e($event['state'] ?? '') ?>"></label>
  <label>URL<input name="url" value="<?= View::e($event['url'] ?? '') ?>"></label>
  <label>Work name<input name="work_name" value="<?= View::e($event['work_name'] ?? '') ?>"></label>
  <label><input type="checkbox" name="is_recent" <?= $event['is_recent'] ? 'checked' : '' ?>> Show on public about page</label>
  <label class="wide">Description / HTML allowed<textarea name="description"><?= View::e($event['description'] ?? '') ?></textarea></label>
  <label class="wide">Additional information / HTML allowed<textarea name="additional_info"><?= View::e($event['additional_info'] ?? '') ?></textarea></label>
  <button>Save event details</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
