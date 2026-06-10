<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;
use PDO;

/**
 * Platform-wide contact message index for support and owner users.
 */
final class ContactMessagesController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $status = trim((string) ($_GET['status'] ?? ''));
        $tenantId = (int) ($_GET['tenant_id'] ?? 0);
        $params = [];
        $where = [];

        if ($status !== '') {
            $where[] = 'cm.status = :status';
            $params['status'] = $status;
        }

        if ($tenantId > 0) {
            $where[] = 'cm.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT cm.*, t.name AS tenant_name, t.slug AS tenant_slug
            FROM contact_messages cm
            JOIN tenants t ON t.id = cm.tenant_id
            {$whereSql}
            ORDER BY cm.created_at DESC, cm.id DESC
            LIMIT 250");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $table = $this->messageTable($rows);
        $statusValue = $this->escape($status);
        $tenantValue = $tenantId > 0 ? (string) $tenantId : '';

        $body = <<<HTML
<p class="admin-muted">Platform-wide contact messages across tenants. Tenant admins should use their tenant domain route: <code>/admin/contact-messages</code>.</p>
<form class="admin-filter-bar" method="get" action="/admin/contact-messages">
    <label>Status<br><input name="status" value="{$statusValue}" placeholder="new, read, archived"></label>
    <label>Tenant ID<br><input type="number" name="tenant_id" value="{$tenantValue}"></label>
    <button type="submit">Filter</button>
    <a href="/admin/contact-messages">Clear</a>
</form>
{$table}
HTML;

        return Response::html(AdminLayout::render('Platform Contact Messages', $body, 'messages'));
    }

    private function messageTable(array $rows): string
    {
        if ($rows === []) {
            return '<p class="admin-muted">No contact messages found.</p>';
        }

        $html = '<table class="admin-table"><thead><tr><th>Tenant</th><th>Status</th><th>Name</th><th>Email</th><th>Message</th><th>Created</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $message = mb_substr((string) ($row['message'] ?? ''), 0, 220);
            $html .= '<tr><td>' . $this->escape((string) $row['tenant_name']) . '<br><small>' . $this->escape((string) $row['tenant_slug']) . '</small></td>'
                . '<td>' . $this->escape((string) ($row['status'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($row['sender_name'] ?? $row['name'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($row['sender_email'] ?? $row['email'] ?? '')) . '</td>'
                . '<td>' . nl2br($this->escape($message)) . '</td>'
                . '<td>' . $this->escape((string) $row['created_at']) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
