<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Support\Csv\CsvResponse;

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
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $action = trim((string) ($_GET['action'] ?? ''));
        $tenantId = $this->positiveIntOrNull($_GET['tenant_id'] ?? null);
        $userId = $this->positiveIntOrNull($_GET['user_id'] ?? null);

        $rows = '';

        foreach ($this->auditLog->search(
            action: $action !== '' ? $action : null,
            tenantId: $tenantId,
            userId: $userId,
            limit: 100,
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
        $exportUrl = '/admin/audit-log.csv?action=' . rawurlencode($action)
            . '&tenant_id=' . rawurlencode($tenantValue)
            . '&user_id=' . rawurlencode($userValue);

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit Log | Platform Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Audit Log</h1>
<p><a href="{$exportUrl}">Export CSV</a></p>

<form method="get" action="/admin/audit-log">
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

<table border="1" cellpadding="6" cellspacing="0">
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

<p><a href="/admin">Back to platform admin</a></p>
</body>
</html>
HTML);
    }

    public function export(Request $request, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser)) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
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
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
