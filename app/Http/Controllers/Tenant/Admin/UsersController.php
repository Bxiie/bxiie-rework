<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Http\View\TenantAdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Identity\AdminUserRepository;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Tenancy\TenantContext;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Tenant-admin user list and local password reset screen.
 */
final class UsersController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly AdminUserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly CsrfTokenService $csrf,
        private readonly TenantSettingsRepository $settings,
        private readonly ?AuditLogRepository $auditLog = null,
        private readonly ?EmailOutboxRepository $emailOutbox = null,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $csrf = $this->escape($this->csrf->getOrCreate());
        $notice = $this->noticeHtml((string) ($_GET['notice'] ?? ''));
        $rows = '';

        foreach ($this->users->tenantUsers($tenant->tenantId) as $user) {
            $id = (int) $user['id'];
            $email = $this->escape((string) $user['email']);
            $name = $this->escape((string) ($user['display_name'] ?? ''));
            $status = $this->escape((string) ($user['membership_status'] ?? ''));
            $roles = $this->escape((string) ($user['roles'] ?? ''));
            $created = $this->escape((string) ($user['created_at'] ?? ''));
            $lastLogin = $this->escape((string) ($user['last_login_at'] ?? 'Never'));
            $canOwnerManage = $this->isTenantOwner($currentUser, $tenant);
            $promoteForm = $canOwnerManage && !str_contains(',' . $roles . ',', ', owner,') ? <<<HTML
        <form method="post" action="/admin/users/promote-owner" class="admin-inline-form" onsubmit="return confirm('Promote this user to tenant owner?');">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="user_id" value="{$id}">
            <button type="submit">Make owner</button>
        </form>
HTML : '';
            $deleteForm = $canOwnerManage && $id !== (int) ($currentUser['user_id'] ?? 0) ? <<<HTML
        <form method="post" action="/admin/users/delete" class="admin-inline-form" onsubmit="this.confirm_delete.value = prompt('Type delete to remove this tenant user from this tenant. This revokes tenant access and writes an audit log entry.') || ''; return this.confirm_delete.value === 'delete';">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="user_id" value="{$id}">
            <input type="hidden" name="confirm_delete" value="">
            <button type="submit">Delete</button>
        </form>
HTML : '';

            $rows .= <<<HTML
<tr>
    <td>{$id}</td>
    <td><strong>{$email}</strong><br><small>{$name}</small></td>
    <td>{$status}</td>
    <td>{$roles}</td>
    <td>{$lastLogin}</td>
    <td>{$created}</td>
    <td>
        <form method="post" action="/admin/users/password" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="user_id" value="{$id}">
            <input type="password" name="new_password" minlength="12" required placeholder="New password">
            <button type="submit">Change password</button>
        </form>
        <form method="post" action="/admin/users/resend-invite" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="user_id" value="{$id}">
            <button type="submit">Resend invite</button>
        </form>
        {$promoteForm}
        {$deleteForm}
    </td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No tenant users found.</td></tr>';
        }

        $body = <<<HTML
{$notice}
<p class="admin-muted">Tenant admins can see tenant users, rotate local passwords, and invite additional tenant admins. Tenant owners can promote admins to owner and delete tenant user access.</p>
<section class="admin-panel">
    <h2>Invite tenant user</h2>
    <form method="post" action="/admin/users/invite" class="admin-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>Email<br><input type="email" name="email" required autocomplete="email"></label>
        <label>Name<br><input type="text" name="display_name" autocomplete="name"></label>
        <label>Access level<br><select name="role"><option value="user">User</option><option value="editor">Editor</option><option value="admin">Tenant admin</option></select></label>
        <button type="submit">Send invite</button>
    </form>
</section>
<table class="admin-table">
    <thead><tr><th>ID</th><th>User</th><th>Status</th><th>Roles</th><th>Last log on</th><th>Created</th><th>Password</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
HTML;

        return Response::html((new TenantAdminLayout($this->settings))->render($tenant, 'Tenant Users', $body, 'users'));
    }

    public function updatePassword(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['new_password'] ?? '');
        if ($userId < 1 || strlen($password) < 12 || !$this->users->userBelongsToTenant($tenant->tenantId, $userId)) {
            return Response::html('<h1>Invalid password update request</h1>', 422);
        }

        $this->users->updatePasswordHash($userId, $this->hasher->hash($password));
        $this->auditLog?->record('tenant.user.password_changed', $tenant->tenantId, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, [], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant user password updated.');

        return new Response('', 303, ['Location' => '/admin/users?notice=password-updated']);
    }


    public function invite(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid invite email address</h1>', 422);
        }

        $role = (string) ($_POST['role'] ?? 'user');
        $userId = $this->users->inviteTenantUser($tenant->tenantId, $email, $role, $displayName !== '' ? $displayName : null);
        $this->queueInviteEmail($tenant, $email, $displayName !== '' ? $displayName : null);
        $this->auditLog?->record('tenant.user.invited', $tenant->tenantId, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['email' => $email, 'role' => $role], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant user invite queued.');

        return new Response('', 303, ['Location' => '/admin/users?notice=invite-queued']);
    }



    public function resendInvite(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $user = $this->findTenantUser($tenant->tenantId, $userId);
        if (!$user) {
            return Response::html('<h1>Invalid tenant user invite resend request</h1>', 422);
        }

        $this->queueInviteEmail($tenant, (string) $user['email'], $user['display_name'] !== null ? (string) $user['display_name'] : null);
        $this->auditLog?->record('tenant.user.invite_resent', $tenant->tenantId, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['email' => (string) $user['email']], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant admin invite resent.');

        return new Response('', 303, ['Location' => '/admin/users?notice=invite-resent']);
    }

    private function findTenantUser(int $tenantId, int $userId): ?array
    {
        foreach ($this->users->tenantUsers($tenantId) as $user) {
            if ((int) $user['id'] === $userId) {
                return $user;
            }
        }

        return null;
    }

    public function promoteOwner(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->isTenantOwner($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant owner access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId < 1 || !$this->users->userBelongsToTenant($tenant->tenantId, $userId)) {
            return Response::html('<h1>Invalid tenant user</h1>', 422);
        }

        $this->users->promoteTenantUserToOwner($tenant->tenantId, $userId);
        $this->auditLog?->record('tenant.user.promoted_owner', $tenant->tenantId, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, [], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant user promoted to owner.');

        return new Response('', 303, ['Location' => '/admin/users?notice=owner-promoted']);
    }

    public function delete(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->isTenantOwner($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant owner access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId < 1 || $userId === (int) ($currentUser['user_id'] ?? 0) || strtolower((string) ($_POST['confirm_delete'] ?? '')) !== 'delete') {
            return Response::html('<h1>Invalid tenant user delete request</h1>', 422);
        }
        if (!$this->users->userBelongsToTenant($tenant->tenantId, $userId)) {
            return Response::html('<h1>Invalid tenant user</h1>', 422);
        }

        $this->users->deleteTenantUser($tenant->tenantId, $userId);
        $this->auditLog?->record('tenant.user.deleted', $tenant->tenantId, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['scope' => 'tenant_membership'], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant user deleted.');

        return new Response('', 303, ['Location' => '/admin/users?notice=user-deleted']);
    }

    private function queueInviteEmail(TenantContext $tenant, string $email, ?string $displayName): void
    {
        if ($this->emailOutbox === null) {
            return;
        }

        $siteName = $this->settings->get($tenant, 'site_title', $tenant->name);
        $loginUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/login';
        $nameLine = $displayName ? "Hello {$displayName}," : 'Hello,';
        $bodyText = "{$nameLine}\n\nYou have been invited to {$siteName}.\n\nOpen {$loginUrl} to sign in. If you do not have a local password yet, use the password reset flow to set one.\n\nArtsFolio";
        $bodyHtml = '<p>' . htmlspecialchars($nameLine, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>You have been invited to ' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Open tenant login</a></p>'
            . '<p>If you do not have a local password yet, use the password reset flow to set one.</p>';

        $this->emailOutbox->queue(
            recipientEmail: $email,
            subject: 'You have been invited to administer ' . $siteName,
            bodyText: $bodyText,
            bodyHtml: $bodyHtml,
            recipientName: $displayName,
            tenantId: $tenant->tenantId,
            templateKey: 'tenant_admin_invite',
        );
    }


    private function isTenantOwner(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'owner']);
    }

    private function noticeHtml(string $notice): string
    {
        $message = match ($notice) {
            'password-updated' => 'Tenant user password updated.',
            'invite-queued' => 'Tenant user invite queued.',
            'invite-resent' => 'Tenant admin invite resent.',
            'owner-promoted' => 'Tenant user promoted to owner.',
            'user-deleted' => 'Tenant user deleted.',
            default => '',
        };

        return $message !== '' ? '<p class="admin-notice admin-notice-success">' . $this->escape($message) . '</p>' : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
