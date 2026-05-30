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
 * Platform-admin user list and local password reset screen.
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
            $status = AdminLayout::escape((string) ($user['user_status'] ?? 'active'));
            $statusActions = $this->userStatusActions($id, (string) ($user['user_status'] ?? 'active'), $csrf);
            $created = AdminLayout::escape((string) ($user['created_at'] ?? ''));
            $lastLogin = AdminLayout::escape((string) ($user['last_login_at'] ?? 'Never'));
            $rows .= <<<HTML
<tr>
    <td>{$id}</td>
    <td><strong>{$email}</strong><br><small>{$name}</small></td>
    <td>{$roles}<br><small>Status: {$status}</small></td>
    <td>{$lastLogin}</td>
    <td>{$created}</td>
    <td>
        <form method="post" action="/platform/admin/users/password" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="user_id" value="{$id}">
            <input type="password" name="new_password" minlength="12" required placeholder="New password">
            <button type="submit">Change password</button>
        </form>
        {$statusActions}
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
    <thead><tr><th>ID</th><th>User</th><th>Roles</th><th>Last log on</th><th>Created</th><th>Password</th><th>Lifecycle</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
<script>document.querySelectorAll('.confirm-platform-user-action').forEach(function(form){form.addEventListener('submit',function(event){var action=form.getAttribute('data-action')||'update';if(!confirm('Confirm '+action+' for this user. This affects access immediately.')){event.preventDefault();}});});</script>
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
        $this->auditLog?->record('platform.user.password_changed', null, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, [], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Platform user password updated.');

        return new Response('', 303, ['Location' => '/platform/admin/users?notice=password-updated']);
    }


    public function updateStatus(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($userId < 1 || !in_array($status, ['active', 'suspended', 'deleted'], true)) {
            return Response::html('<h1>Invalid user status request</h1>', 422);
        }

        $this->users->setUserStatus($userId, $status);
        $this->auditLog?->record('platform.user.status_changed', null, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['status' => $status], $request->server('REMOTE_ADDR'));
        FlashMessages::success("User {$userId} status changed to {$status}.");

        return new Response('', 303, ['Location' => '/platform/admin/users?notice=status-updated']);
    }

    private function userStatusActions(int $userId, string $status, string $csrf): string
    {
        $buttons = '';
        foreach (['active' => 'Reactivate', 'suspended' => 'Suspend', 'deleted' => 'Delete'] as $target => $label) {
            if ($target === $status) {
                continue;
            }
            $buttons .= '<form method="post" action="/platform/admin/users/status" class="admin-inline-form">'
                . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                . '<input type="hidden" name="user_id" value="' . $userId . '">'
                . '<input type="hidden" name="status" value="' . AdminLayout::escape($target) . '">'
                . '<button type="submit">' . AdminLayout::escape($label) . '</button>'
                . '</form>';
        }

        return $buttons;
    }

    public function suspend(Request $request, ?array $currentUser): Response
    {
        return $this->lifecycle($request, $currentUser, 'suspend');
    }

    public function delete(Request $request, ?array $currentUser): Response
    {
        return $this->lifecycle($request, $currentUser, 'delete');
    }

    private function lifecycle(Request $request, ?array $currentUser, string $action): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId < 1 || !$this->users->userIsPlatformUser($userId)) {
            return Response::html('<h1>Invalid user lifecycle request</h1>', 422);
        }
        if ($action === 'delete') {
            $this->users->deleteUser($userId);
        } else {
            $this->users->suspendUser($userId);
        }
        $this->auditLog?->record('platform.user.' . $action, null, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, [], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Platform user ' . $action . 'd.');
        return new Response('', 303, ['Location' => '/platform/admin/users?notice=' . $action]);
    }

    private function canManageUsers(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }
}

// End of file.
