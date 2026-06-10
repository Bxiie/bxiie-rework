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
 * Platform-level message surface for support and tenant contact visibility.
 */
final class ContactMessagesController
{
    public function __construct(private readonly RequirePlatformRole $roles, private readonly PDO $pdo) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $rows = '';
        if ($this->tableExists('contact_messages')) {
            $stmt = $this->pdo->query('SELECT cm.created_at, cm.name, cm.email, cm.subject, cm.status, t.slug AS tenant_slug FROM contact_messages cm LEFT JOIN tenants t ON t.id = cm.tenant_id ORDER BY cm.id DESC LIMIT 100');
            foreach ($stmt->fetchAll() as $message) {
                $rows .= '<tr><td>' . AdminLayout::escape((string) $message['created_at']) . '</td><td>' . AdminLayout::escape((string) ($message['tenant_slug'] ?? '')) . '</td><td>' . AdminLayout::escape((string) ($message['name'] ?? '')) . '</td><td>' . AdminLayout::escape((string) ($message['email'] ?? '')) . '</td><td>' . AdminLayout::escape((string) ($message['subject'] ?? '')) . '</td><td>' . AdminLayout::escape((string) ($message['status'] ?? '')) . '</td></tr>';
            }
        }
        if ($rows === '') { $rows = '<tr><td colspan="6">No platform-visible contact messages found.</td></tr>'; }

        return Response::html(AdminLayout::render(title: 'Platform Contact Messages', body: <<<HTML
<p>Latest tenant contact messages across the platform. Tenant admins still manage workflow status from their own domain.</p>
<table class="admin-table"><thead><tr><th>Date</th><th>Tenant</th><th>Name</th><th>Email</th><th>Subject</th><th>Status</th></tr></thead><tbody>{$rows}</tbody></table>
HTML, active: 'messages'));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

// End of file.
