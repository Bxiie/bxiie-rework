<?php ob_start(); use App\Core\View; ?>
<h2>Events and exhibitions</h2>
<form method="post" class="panel grid2">
  <label>Exhibition name<input name="title" required></label>
  <label>Date display<input name="display_date" placeholder="January 2025"></label>
  <label>Sort date<input name="event_date" type="date"></label>
  <label>Type<input name="event_type" placeholder="Juried Exhibition"></label>
  <label>City<input name="city"></label>
  <label>State/Country<input name="state"></label>
  <label>Work name<input name="work_name"></label>
  <label>URL<input name="url"></label>
  <label><input type="checkbox" name="is_recent" checked> Show</label>
  <label class="wide">Additional information<textarea name="additional_info"></textarea></label>
  <button>Add event</button>
</form>
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
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
