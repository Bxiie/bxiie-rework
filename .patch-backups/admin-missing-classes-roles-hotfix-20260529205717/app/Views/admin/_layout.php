<?php use App\Core\View; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · <?= View::e($tenant['display_name']) ?></title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<aside>
  <h1><?= View::e($tenant['display_name']) ?></h1>
  <a href="/admin">Dashboard</a>
  <a href="/admin/site">Site</a>
  <a href="/admin/images">Images</a>
  <a href="/admin/portfolio">Portfolio</a>
  <a href="/admin/events">Exhibitions</a>
  <a href="/admin/content">Content</a>
  <a href="/admin/messages">Messages</a>
  <a href="/admin/subscribers">Email List</a>
  <a href="/admin/users">Users</a>
  <a href="/admin/stats">Stats</a>
  <a href="/admin/logout">Logout</a>
</aside>
<main>
  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="notice"><?= View::e($_SESSION['flash']) ?></p>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
  <?= $content ?>
</main>
</body>
</html>
<?php // End of file. ?>
