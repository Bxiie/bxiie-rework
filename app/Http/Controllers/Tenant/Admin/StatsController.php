<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Tenancy\TenantContext;
use PDO;

final class StatsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
        $rangeStart = date('Y-m-d H:i:s', time() - ($days * 86400));

        $eventCount = $this->countTable('analytics_events', $tenant->tenantId, $rangeStart);
        $artworkCount = $this->countSimple('artworks', $tenant->tenantId);
        $signupCount = $this->countSimple('email_signups', $tenant->tenantId);
        $messageCount = $this->countSimple('contact_messages', $tenant->tenantId);

        $body = <<<HTML
<a class="admin-back" href="/admin">&larr; Admin</a>
<p class="admin-muted">Stats v1. This restores the admin route and gives us a landing pad for day/hour graphs, thumbnails, and location rollups.</p>
<form class="admin-toolbar" method="get" action="/admin/stats">
    <label>Range
        <select name="days">
            <option value="7"{$this->selected($days, 7)}>Last 7 days</option>
            <option value="30"{$this->selected($days, 30)}>Last 30 days</option>
            <option value="90"{$this->selected($days, 90)}>Last 90 days</option>
            <option value="365"{$this->selected($days, 365)}>Last year</option>
        </select>
    </label>
    <button type="submit">Apply</button>
</form>
<div class="dashboard-grid">
    <section class="dashboard-card"><h3>Events in range</h3><p>{$eventCount}</p></section>
    <section class="dashboard-card"><h3>Artworks</h3><p>{$artworkCount}</p></section>
    <section class="dashboard-card"><h3>Email signups</h3><p>{$signupCount}</p></section>
    <section class="dashboard-card"><h3>Contact messages</h3><p>{$messageCount}</p></section>
</div>
HTML;

        return Response::html(AdminLayout::render('Stats', $body));
    }

    private function selected(int $actual, int $expected): string
    {
        return $actual === $expected ? ' selected' : '';
    }

    private function countSimple(string $table, int $tenantId): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private function countTable(string $table, int $tenantId, string $rangeStart): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        $hasCreatedAt = $this->columnExists($table, 'created_at');
        $sql = "SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id";
        $params = ['tenant_id' => $tenantId];

        if ($hasCreatedAt) {
            $sql .= " AND created_at >= :range_start";
            $params['range_start'] = $rangeStart;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name');
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

// End of file.
