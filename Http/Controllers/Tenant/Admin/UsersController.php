<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Http\View\TenantAdminLayout;
use App\Platform\Audit\AuditLogRepository;
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
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $csrf = $this->escape($this->csrf->getOrCreate());
        $notice = isset($_GET['notice']) ? '<p class="admin-notice admin-notice-success">Tenant user password updated.</p>' : '';
        $rows = '';

        foreach ($this->users->tenantUsers($tenant->tenantId) as $user) {
            $id = (int) $user['id'];
            $email = $this->escape((string) $user['email']);
            $name = $this->escape((string) ($user['display_name'] ?? ''));
            $status = $this->escape((string) ($user['membership_status'] ?? ''));
            $roles = $this->escape((string) ($user['roles'] ?? ''));
            $created = $this->escape((string) ($user['created_at'] ?? ''));
            $lastLogin = $this->escape((string) ($user['last_login_at'] ?? 'Never'));
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
    </td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No tenant users found.</td></tr>';
        }

        $body = <<<HTML
{$notice}
<p class="admin-muted">Tenant admins can see tenant users and rotate local passwords. This screen does not grant or remove roles.</p>
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
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['new_password'] ?? '');
        if ($userId < 1 || strlen($password) < 12 || !$this->users->userBelongsToTenant($tenant->tenantId, $userId)) {
            return Response::html('<h1>Invalid password update request</h1>', 422);
        }

        $this->users->updatePasswordHash($userId, $this->hasher->hash($password));
        $this->auditLog?->record('tenant.user.password_changed', (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['tenant_id' => $tenant->tenantId], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant user password updated.');

        return new Response('', 303, ['Location' => '/admin/users?notice=password-updated']);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
