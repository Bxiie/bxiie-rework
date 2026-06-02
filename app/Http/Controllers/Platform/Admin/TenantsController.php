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
use App\Platform\Tenants\TenantAdminRepository;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;

/**
 * Handles platform-admin tenant list and tenant drill-in screens.
 */
final class TenantsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly TenantAdminRepository $tenants,
        private readonly ?AdminUserRepository $users = null,
        private readonly ?PasswordHasher $hasher = null,
        private readonly ?CsrfTokenService $csrf = null,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canViewTenants($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $rows = '';
        foreach ($this->tenants->latest() as $tenant) {
            $id = (int) $tenant['id'];
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $id) . '</td>'
                . '<td><a href="/platform/admin/tenants/' . $id . '">' . $this->escape((string) $tenant['slug']) . '</a></td>'
                . '<td>' . $this->escape((string) $tenant['name']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['status']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['domain_count']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['created_at']) . '</td>'
                . '<td>' . ($this->csrf ? $this->tenantStatusActions($id, (string) $tenant['status'], $this->escape($this->csrf->getOrCreate())) : '') . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No tenants found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: 'Tenants',
            active: 'tenants',
            body: <<<HTML
<p class="admin-muted">Open a tenant to review tenant users, email addresses, names, roles, membership status, and last log on timestamps.</p>
<table class="admin-table">
    <thead><tr><th>ID</th><th>Slug</th><th>Name</th><th>Status</th><th>Domains</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
<script>document.querySelectorAll('.confirm-platform-tenant-action').forEach(function(form){form.addEventListener('submit',function(event){var action=form.getAttribute('data-action')||'update';if(!confirm('Confirm '+action+' for this tenant. Suspended tenant resources will show the ArtsFolio unavailable page.')){event.preventDefault();}});});</script>
HTML,
        ));
    }

    public function show(Request $request, ?array $currentUser, int $tenantId): Response
    {
        if (!$this->canViewTenants($currentUser) || !$this->users || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $tenant = $this->findTenant($tenantId);
        if (!$tenant) {
            return Response::html('<h1>Tenant not found</h1>', 404);
        }

        $csrf = $this->escape($this->csrf->getOrCreate());
        $tenantName = $this->escape((string) $tenant['name']);
        $tenantSlug = $this->escape((string) $tenant['slug']);
        $tenantUrl = $this->tenants->publicUrlForTenant($tenantId);
        $tenantLink = $tenantUrl !== null
            ? '<p><a class="button secondary" target="_blank" rel="noopener" href="' . $this->escape($tenantUrl) . '">Open tenant site in new tab</a></p>'
            : '<p class="admin-muted">No active tenant subdomain is currently available.</p>';
        $notice = isset($_GET['notice']) ? '<p class="admin-notice admin-notice-success">Tenant user password updated.</p>' : '';
        $rows = '';

        foreach ($this->users->tenantUsers($tenantId) as $user) {
            $id = (int) $user['id'];
            $email = $this->escape((string) $user['email']);
            $name = $this->escape((string) ($user['display_name'] ?? ''));
            $status = $this->escape((string) ($user['membership_status'] ?? ''));
            $roles = $this->escape((string) ($user['roles'] ?? ''));
            $lastLogin = $this->escape((string) ($user['last_login_at'] ?? 'Never'));
            $rows .= <<<HTML
<tr>
    <td>{$id}</td><td><strong>{$email}</strong><br><small>{$name}</small></td><td>{$status}</td><td>{$roles}</td><td>{$lastLogin}</td>
    <td><form method="post" action="/platform/admin/tenants/users/password" class="admin-inline-form"><input type="hidden" name="csrf_token" value="{$csrf}"><input type="hidden" name="tenant_id" value="{$tenantId}"><input type="hidden" name="user_id" value="{$id}"><input type="password" name="new_password" minlength="12" required placeholder="New password"><button type="submit">Change password</button></form></td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">No tenant users found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: "Tenant: {$tenantName}",
            active: 'tenants',
            body: <<<HTML
<p><a href="/platform/admin/tenants">&larr; Tenants</a></p>
{$notice}
<p><strong>Slug:</strong> {$tenantSlug}</p>
{$tenantLink}
<table class="admin-table">
    <thead><tr><th>ID</th><th>User</th><th>Membership</th><th>Roles</th><th>Last log on</th><th>Password</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
HTML,
        ));
    }

    public function updateTenantUserPassword(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->users || !$this->hasher || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['new_password'] ?? '');
        if ($tenantId < 1 || $userId < 1 || strlen($password) < 12 || !$this->users->userBelongsToTenant($tenantId, $userId)) {
            return Response::html('<h1>Invalid password update request</h1>', 422);
        }

        $this->users->updatePasswordHash($userId, $this->hasher->hash($password));
        $this->auditLog?->record('platform.tenant_user.password_changed', $tenantId, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['tenant_id' => $tenantId], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant user password updated.');

        return new Response('', 303, ['Location' => '/platform/admin/tenants/' . $tenantId . '?notice=password-updated']);
    }


    public function updateTenantStatus(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($tenantId < 1 || !in_array($status, ['trial', 'active', 'suspended', 'archived'], true)) {
            return Response::html('<h1>Invalid tenant status request</h1>', 422);
        }

        $this->tenants->setStatus($tenantId, $status);
        $this->auditLog?->record('platform.tenant.status_changed', $tenantId, (int) ($currentUser['user_id'] ?? 0), 'tenant', (string) $tenantId, ['status' => $status], $request->server('REMOTE_ADDR'));
        FlashMessages::success("Tenant {$tenantId} status changed to {$status}.");

        return new Response('', 303, ['Location' => '/platform/admin/tenants?notice=status-updated']);
    }

    private function tenantStatusActions(int $tenantId, string $status, string $csrf): string
    {
        $targets = ['active' => 'Activate', 'suspended' => 'Suspend'];
        $html = '';
        foreach ($targets as $target => $label) {
            if ($target === $status) {
                continue;
            }
            $html .= '<form method="post" action="/platform/admin/tenants/status" class="admin-inline-form">'
                . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                . '<input type="hidden" name="tenant_id" value="' . $tenantId . '">'
                . '<input type="hidden" name="status" value="' . $this->escape($target) . '">'
                . '<button type="submit">' . $this->escape($label) . '</button>'
                . '</form>';
        }

        $html .= '<form method="post" action="/platform/admin/tenants/delete" class="admin-inline-form" onsubmit="this.confirm_delete.value = prompt(&quot;Type delete to remove this tenant from active platform lists. Data is soft-deleted, not physically destroyed.&quot;) || &quot;&quot;; return this.confirm_delete.value === &quot;delete&quot;;">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<input type="hidden" name="tenant_id" value="' . $tenantId . '">'
            . '<input type="hidden" name="confirm_delete" value="">'
            . '<button type="submit">Delete</button>'
            . '</form>';

        return $html;
    }

    public function suspend(Request $request, ?array $currentUser): Response
    {
        return $this->tenantLifecycle($request, $currentUser, 'suspend');
    }

    public function delete(Request $request, ?array $currentUser): Response
    {
        return $this->tenantLifecycle($request, $currentUser, 'delete');
    }

    private function tenantLifecycle(Request $request, ?array $currentUser, string $action): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return Response::html('<h1>Invalid tenant lifecycle request</h1>', 422);
        }
        if ($action === 'delete' && strtolower((string) ($_POST['confirm_delete'] ?? '')) !== 'delete') {
            return Response::html('<h1>Tenant delete confirmation required</h1>', 422);
        }
        if ($action === 'delete') {
            $this->tenants->deleteTenant($tenantId);
        } else {
            $this->tenants->suspendTenant($tenantId);
        }
        $this->auditLog?->record('platform.tenant.' . $action, null, (int) ($currentUser['user_id'] ?? 0), 'tenant', (string) $tenantId, [], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant ' . $action . 'd.');
        return new Response('', 303, ['Location' => '/platform/admin/tenants?notice=' . $action]);
    }

    private function findTenant(int $tenantId): ?array
    {
        foreach ($this->tenants->latest(500) as $tenant) {
            if ((int) $tenant['id'] === $tenantId) {
                return $tenant;
            }
        }

        return null;
    }

    private function canViewTenants(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT]);
    }

    private function canManageTenants(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
