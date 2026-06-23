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

/** Lists tenant email signups across the platform for authorized platform staff. */
final class EmailSignupsController
{
    public function __construct(private readonly RequirePlatformRole $roles, private readonly PDO $pdo) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT, 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $params = [];
        $where = '';
        if ($q !== '') {
            $where = 'WHERE es.email LIKE :q OR es.name LIKE :q OR es.source LIKE :q OR t.name LIKE :q OR t.slug LIKE :q';
            $params['q'] = '%' . $q . '%';
        }
        $stmt = $this->pdo->prepare("SELECT es.*, t.name AS tenant_name, t.slug AS tenant_slug FROM email_signups es JOIN tenants t ON t.id = es.tenant_id {$where} ORDER BY es.created_at DESC, es.id DESC LIMIT 500");
        $stmt->execute($params);
        $rows = '';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tenant = $this->escape((string) ($row['tenant_name'] ?? $row['tenant_slug'] ?? ''));
            $email = $this->escape((string) ($row['email'] ?? ''));
            $name = $this->escape((string) ($row['name'] ?? ''));
            $source = $this->escape((string) ($row['source'] ?? ''));
            $status = $this->escape((string) ($row['consent_status'] ?? ''));
            $created = $this->escape((string) ($row['created_at'] ?? ''));
            $rows .= '<tr><td>' . $created . '</td><td>' . $tenant . '</td><td><a href="mailto:' . $email . '">' . $email . '</a></td><td>' . $name . '</td><td>' . $source . '</td><td>' . $status . '</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="6">No email signups match this search.</td></tr>';
        $this->markViewed();
        $qv = $this->escape($q);
        $body = '<p class="admin-muted">Tenant email-list signups across ArtsFolio. Manage a record from its tenant admin.</p>'
            . '<form method="get" action="/platform/admin/email-signups" class="admin-filter-bar"><label>Search<br><input type="search" name="q" value="' . $qv . '" placeholder="Tenant, email, name, source"></label><button type="submit">Apply</button><a href="/platform/admin/email-signups">Clear</a></form>'
            . '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Tenant</th><th>Email</th><th>Name</th><th>Source</th><th>Consent</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
        return Response::html(AdminLayout::render(title: 'Email Signups', body: $body, active: 'email_signups'));
    }

    private function markViewed(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value, created_at, updated_at) VALUES ('email_signups_last_viewed_at', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE setting_value = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()");
        $stmt->execute();
    }

    private function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
