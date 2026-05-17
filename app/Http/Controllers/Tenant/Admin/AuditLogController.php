<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;
use App\Support\Csv\CsvResponse;

/**
 * Handles tenant-admin audit log list and export screens.
 */
final class AuditLogController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly AuditLogRepository $auditLog,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $action = trim((string) ($_GET['action'] ?? ''));
        $userId = $this->positiveIntOrNull($_GET['user_id'] ?? null);
        $rows = '';

        foreach ($this->auditLog->search(
            action: $action !== '' ? $action : null,
            tenantId: $tenant->tenantId,
            userId: $userId,
            limit: 100,
        ) as $event) {
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $event['id']) . '</td>'
                . '<td>' . $this->escape((string) ($event['user_id'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $event['action']) . '</td>'
                . '<td>' . $this->escape((string) ($event['entity_type'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($event['entity_id'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($event['ip_address'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $event['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No tenant audit log rows found.</td></tr>';
        }

        $actionValue = $this->escape($action);
        $userValue = $userId !== null ? $this->escape((string) $userId) : '';
        $exportUrl = '/admin/audit-log.csv?action=' . rawurlencode($action)
            . '&user_id=' . rawurlencode($userValue);
        $tenantName = $this->escape($tenant->name);

        return Response::html(AdminLayout::render(
            title: 'Tenant Audit Log | ' . $tenantName,
            body: <<<HTML
<p><a class="admin-button" href="{$exportUrl}">Export CSV</a></p>

<form class="admin-form" method="get" action="/admin/audit-log">
    <p>
        <label>Action<br>
            <input type="text" name="action" value="{$actionValue}">
        </label>
    </p>
    <p>
        <label>User ID<br>
            <input type="number" name="user_id" value="{$userValue}">
        </label>
    </p>
    <button type="submit">Filter</button>
    <a href="/admin/audit-log">Clear</a>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity Type</th>
            <th>Entity ID</th>
            <th>IP</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
HTML,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/settings' => 'Settings',
                '/admin/contact-messages' => 'Contact Messages',
                '/admin/email-signups' => 'Email Signups',
                '/admin/audit-log' => 'Audit Log',
            ],
        ));
    }

    public function export(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $action = trim((string) ($_GET['action'] ?? ''));
        $userId = $this->positiveIntOrNull($_GET['user_id'] ?? null);
        $rows = [];

        foreach ($this->auditLog->search(
            action: $action !== '' ? $action : null,
            tenantId: $tenant->tenantId,
            userId: $userId,
            limit: 5000,
        ) as $event) {
            $rows[] = [
                'id' => (string) $event['id'],
                'user_id' => (string) ($event['user_id'] ?? ''),
                'action' => (string) $event['action'],
                'entity_type' => (string) ($event['entity_type'] ?? ''),
                'entity_id' => (string) ($event['entity_id'] ?? ''),
                'details' => (string) ($event['details'] ?? ''),
                'ip_address' => (string) ($event['ip_address'] ?? ''),
                'created_at' => (string) $event['created_at'],
            ];
        }

        return CsvResponse::download(
            filename: 'tenant-audit-log-' . $tenant->slug . '.csv',
            headers: ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'details', 'ip_address', 'created_at'],
            rows: $rows,
        );
    }

    private function canView(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows(
            currentUser: $currentUser,
            tenant: $tenant,
            allowedRoles: [Roles::TENANT_OWNER, Roles::TENANT_ADMIN],
        );
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
