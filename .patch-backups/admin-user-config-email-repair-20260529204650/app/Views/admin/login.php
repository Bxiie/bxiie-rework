<?php use App\Core\View; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Login</title><link rel="stylesheet" href="/assets/admin.css"></head><body class="login"><form method="post" class="panel"><h1><?= View::e($tenant['display_name']) ?> Admin</h1><?php if ($error): ?><p class="error"><?= View::e($error) ?></p><?php endif; ?><label>Email <input name="email" type="email" required></label><label>Password <input name="password" type="password" required></label><button>Login</button></form></body></html>
<?php // End of file. ?>
