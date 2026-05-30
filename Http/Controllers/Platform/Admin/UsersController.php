<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Identity\AdminUserRepository;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Membership\Roles;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;

/**
 * Platform-admin user list and password reset screen.
 */
final class UsersController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly AdminUserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $notice = isset($_GET['notice']) ? '<p class="admin-notice admin-notice-success">Platform user password updated.</p>' : '';
        $rows = '';

        foreach ($this->users->platformUsers() as $user) {
            $id = (int) $user['id'];
            $email = AdminLayout::escape((string) $user['email']);
            $name = AdminLayout::escape((string) ($user['display_name'] ?? ''));
            $roles = AdminLayout::escape((string) ($user['roles'] ?? ''));
            $created = AdminLayout::escape((string) ($user['created_at'] ?? ''));
            $lastLogin = AdminLayout::escape((string) ($user['last_login_at'] ?? 'Never'));
            $rows .= <<<HTML
<tr>
    <td>{$id}</td>
    <td><strong>{$email}</strong><br><small>{$name}</small></td>
    <td>{$roles}</td>
    <td>{$lastLogin}</td>
    <td>{$created}</td>
    <td>
        <form method="post" action="/platform/admin/users/password" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="user_id" value="{$id}">
            <input type="password" name="new_password" minlength="12" required placeholder="New password">
            <button type="submit">Change password</button>
        </form>
    </td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">No platform users found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: 'Platform Users',
            active: 'users',
            body: <<<HTML
{$notice}
<p class="admin-muted">This screen lists users with platform-scoped roles and allows platform admins to rotate local passwords. It does not edit roles.</p>
<table class="admin-table">
    <thead><tr><th>ID</th><th>User</th><th>Roles</th><th>Last log on</th><th>Created</th><th>Password</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
HTML,
        ));
    }

    public function updatePassword(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['new_password'] ?? '');
        if ($userId < 1 || strlen($password) < 12 || !$this->users->userIsPlatformUser($userId)) {
            return Response::html('<h1>Invalid password update request</h1>', 422);
        }

        $this->users->updatePasswordHash($userId, $this->hasher->hash($password));
        $this->auditLog?->record('platform.user.password_changed', (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, [], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Platform user password updated.');

        return new Response('', 303, ['Location' => '/platform/admin/users?notice=password-updated']);
    }

    private function canManageUsers(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }
}

// End of file.
