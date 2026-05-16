<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;

/**
 * Handles platform-admin audit log list screen.
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
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $rows = '';

        foreach ($this->auditLog->latest(100) as $event) {
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
