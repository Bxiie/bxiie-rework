<?php ob_start(); use App\Core\View; ?>
<h2>Users</h2><form method="post" class="panel grid2"><label>Name<input name="name" required></label><label>Email<input name="email" type="email" required></label><label>Role<select name="role"><option>owner</option><option>editor</option><option>viewer</option></select></label><label>Password<input name="password" type="password" required></label><button>Add user</button></form><table><tr><th>Name</th><th>Email</th><th>Role</th></tr><?php foreach ($users as $user): ?><tr><td><?= View::e($user['name']) ?></td><td><?= View::e($user['email']) ?></td><td><?= View::e($user['role']) ?></td></tr><?php endforeach; ?></table>
<?php $content = ob_get_clean(); require __DIR__ . '/_layout.php'; ?>
<?php // End of file. ?>
