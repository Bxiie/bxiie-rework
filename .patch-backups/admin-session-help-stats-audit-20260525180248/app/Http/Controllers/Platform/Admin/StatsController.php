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
 * Basic platform-level statistics landing page.
 */
final class StatsController
{
    public function __construct(private readonly RequirePlatformRole $roles, private readonly PDO $pdo) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $tenantCount = $this->countTable('tenants');
        $userCount = $this->countTable('users');
        $artworkCount = $this->countTable('artworks');
        $eventCount = $this->countTable('analytics_events');

        return Response::html(AdminLayout::render(title: 'Platform Stats', body: <<<HTML
<div class="feature-grid">
<article><h3>Tenants</h3><p>{$tenantCount}</p></article>
<article><h3>Users</h3><p>{$userCount}</p></article>
<article><h3>Artworks</h3><p>{$artworkCount}</p></article>
<article><h3>Analytics events</h3><p>{$eventCount}</p></article>
</div>
<p>This is the platform rollup. Tenant traffic detail remains under each tenant's <a href="/admin/stats">tenant stats</a> page on the tenant domain.</p>
HTML, active: 'stats'));
    }

    private function countTable(string $table): int
    {
        if (!$this->tableExists($table)) { return 0; }
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

// End of file.
