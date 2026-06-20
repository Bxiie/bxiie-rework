<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Email\EmailOutboxRepository;
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
        private readonly ?EmailOutboxRepository $emailOutbox = null,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $noticeText = match ((string) ($_GET['notice'] ?? '')) {
            'password-updated' => 'Platform user password updated.',
            'status-updated' => 'Platform user status updated.',
            'invite-queued' => 'Platform admin invite queued.',
            'invite-resent' => 'Platform admin invite resent.',
            default => '',
        };
        $notice = $noticeText !== '' ? '<p class="admin-notice admin-notice-success">' . AdminLayout::escape($noticeText) . '</p>' : '';
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
        <form method="post" action="/platform/admin/users/resend-invite" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="user_id" value="{$id}">
            <button type="submit">Resend invite</button>
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
<section class="admin-panel">
    <h2>Invite platform admin</h2>
    <form method="post" action="/platform/admin/users/invite" class="admin-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>Email<br><input type="email" name="email" required autocomplete="email"></label>
        <label>Name<br><input type="text" name="display_name" autocomplete="name"></label>
        <button type="submit">Send invite</button>
    </form>
</section>
<table class="admin-table">
    <thead><tr><th>ID</th><th>User</th><th>Roles</th><th>Last log on</th><th>Created</th><th>Password</th><th>Lifecycle</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
<script>document.querySelectorAll('.confirm-platform-user-action').forEach(function(form){form.addEventListener('submit',function(event){var action=form.getAttribute('data-action')||'update';if(!confirm('Confirm '+action+' for this user. This affects access immediately.')){event.preventDefault();}});});</script>
HTML,
        ));
    }


    public function invite(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid invite email address</h1>', 422);
        }

        $userId = $this->users->invitePlatformUser($email, $displayName !== '' ? $displayName : null);
        $this->queueInviteEmail($email, $displayName !== '' ? $displayName : null);
        $this->auditLog?->record('platform.user.invited_admin', null, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['email' => $email], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Platform admin invite queued.');

        return new Response('', 303, ['Location' => '/platform/admin/users?notice=invite-queued']);
    }

    public function resendInvite(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $user = $this->findPlatformUser($userId);
        if (!$user) {
            return Response::html('<h1>Invalid platform user invite resend request</h1>', 422);
        }

        $this->queueInviteEmail((string) $user['email'], $user['display_name'] !== null ? (string) $user['display_name'] : null);
        $this->auditLog?->record('platform.user.invite_resent', null, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['email' => (string) $user['email']], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Platform admin invite resent.');

        return new Response('', 303, ['Location' => '/platform/admin/users?notice=invite-resent']);
    }

    private function findPlatformUser(int $userId): ?array
    {
        foreach ($this->users->platformUsers() as $user) {
            if ((int) $user['id'] === $userId) {
                return $user;
            }
        }

        return null;
    }

    private function queueInviteEmail(string $email, ?string $displayName): void
    {
        if ($this->emailOutbox === null) {
            return;
        }

        $loginUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'artsfol.io') . '/login';
        $nameLine = $displayName ? "Hello {$displayName}," : 'Hello,';
        $bodyText = "{$nameLine}\n\nYou have been invited to administer ArtsFolio.\n\nOpen {$loginUrl} to sign in. If you do not have a local password yet, use the password reset flow to set one.\n\nArtsFolio";
        $bodyHtml = '<p>' . htmlspecialchars($nameLine, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>You have been invited to administer ArtsFolio.</p>'
            . '<p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Open platform login</a></p>'
            . '<p>If you do not have a local password yet, use the password reset flow to set one.</p>';

        $this->emailOutbox->queue(
            recipientEmail: $email,
            subject: 'You have been invited to administer ArtsFolio',
            bodyText: $bodyText,
            bodyHtml: $bodyHtml,
            recipientName: $displayName,
            tenantId: null,
            templateKey: 'platform_admin_invite',
        );
    }

    public function updatePassword(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageUsers($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
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
            return Response::invalidCsrf();
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
            return Response::invalidCsrf();
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
