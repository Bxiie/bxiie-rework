<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Support\Csv\CsvResponse;
use App\Support\Pagination\Pagination;

/**
 * Handles platform-admin audit log list and export screens.
 */
final class AuditLogController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly AuditLogRepository $auditLog,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $action = trim((string) ($_GET['action'] ?? ''));
        $tenantId = $this->positiveIntOrNull($_GET['tenant_id'] ?? null);
        $userId = $this->positiveIntOrNull($_GET['user_id'] ?? null);
        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);

        $rows = '';

        foreach ($this->auditLog->search(
            action: $action !== '' ? $action : null,
            tenantId: $tenantId,
            userId: $userId,
            limit: $limit,
            offset: $offset,
        ) as $event) {
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $event['id']) . '</td>'
                . '<td>' . $this->escape((string) ($event['tenant_id'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($event['user_id'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $event['action']) . '</td>'
                . '<td>' . $this->escape((string) ($event['entity_type'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($event['entity_id'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($event['ip_address'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $event['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8">No audit log rows found.</td></tr>';
        }

        $actionValue = $this->escape($action);
        $tenantValue = $tenantId !== null ? $this->escape((string) $tenantId) : '';
        $userValue = $userId !== null ? $this->escape((string) $userId) : '';
        $query = ['action' => $action, 'tenant_id' => $tenantValue, 'user_id' => $userValue, 'limit' => $limit];
        $exportUrl = '/admin/audit-log.csv?' . http_build_query($query);
        $prevUrl = Pagination::previousPageUrl('/admin/audit-log', $query, $page);
        $nextUrl = Pagination::nextPageUrl('/admin/audit-log', $query, $page);
        $pager = '<p>'
            . ($prevUrl ? '<a class="admin-button" href="' . $this->escape($prevUrl) . '">Previous</a> ' : '')
            . '<span class="admin-muted">Page ' . $page . '</span> '
            . '<a class="admin-button" href="' . $this->escape($nextUrl) . '">Next</a>'
            . '</p>';

        return Response::html(AdminLayout::render(
            title: 'Audit Log | Platform Admin',
            body: <<<HTML
<p><a class="admin-button" href="{$exportUrl}">Export CSV</a></p>

<form class="admin-form" method="get" action="/admin/audit-log">
    <p>
        <label>Action<br>
            <input type="text" name="action" value="{$actionValue}">
        </label>
    </p>
    <p>
        <label>Tenant ID<br>
            <input type="number" name="tenant_id" value="{$tenantValue}">
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
            <th>Tenant</th>
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
                '/admin/tenants' => 'Tenants',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
            ],
        ));
    }

    public function export(Request $request, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $action = trim((string) ($_GET['action'] ?? ''));
        $tenantId = $this->positiveIntOrNull($_GET['tenant_id'] ?? null);
        $userId = $this->positiveIntOrNull($_GET['user_id'] ?? null);

        $rows = [];

        foreach ($this->auditLog->search(
            action: $action !== '' ? $action : null,
            tenantId: $tenantId,
            userId: $userId,
            limit: 5000,
            offset: 0,
        ) as $event) {
            $rows[] = [
                'id' => (string) $event['id'],
                'tenant_id' => (string) ($event['tenant_id'] ?? ''),
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
            filename: 'platform-audit-log.csv',
            headers: ['id', 'tenant_id', 'user_id', 'action', 'entity_type', 'entity_id', 'details', 'ip_address', 'created_at'],
            rows: $rows,
        );
    }

    private function canView(?array $currentUser): bool
    {
        return $this->roles->allows(
            currentUser: $currentUser,
            allowedRoles: [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT],
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
