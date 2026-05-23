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
use App\Support\Pagination\Pagination;

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
        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);
        $rows = '';

        foreach ($this->auditLog->search(
            action: $action !== '' ? $action : null,
            tenantId: $tenant->tenantId,
            userId: $userId,
            limit: $limit,
            offset: $offset,
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
        $query = ['action' => $action, 'user_id' => $userValue, 'limit' => $limit];
        $exportUrl = '/admin/audit-log.csv?' . http_build_query($query);
        $prevUrl = Pagination::previousPageUrl('/admin/audit-log', $query, $page);
        $nextUrl = Pagination::nextPageUrl('/admin/audit-log', $query, $page);
        $pager = '<p>'
            . ($prevUrl ? '<a class="admin-button" href="' . $this->escape($prevUrl) . '">Previous</a> ' : '')
            . '<span class="admin-muted">Page ' . $page . '</span> '
            . '<a class="admin-button" href="' . $this->escape($nextUrl) . '">Next</a>'
            . '</p>';
        $tenantName = $this->escape($tenant->name);

        $body = isset($html) ? (string) $html : '';
        return Response::html(AdminLayout::render('Audit Log', $body));
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
            offset: 0,
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
