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
 * Platform-wide analytics dashboard for support and owner users.
 */
final class StatsController
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

        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
        $sinceSql = "DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        $totalEvents = $this->scalar("SELECT COUNT(*) FROM analytics_events WHERE created_at >= {$sinceSql}");
        $uniqueTenants = $this->scalar("SELECT COUNT(DISTINCT tenant_id) FROM analytics_events WHERE created_at >= {$sinceSql}");
        $imageViews = $this->scalar("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'image_view' AND created_at >= {$sinceSql}");
        $tenantRows = $this->rows("SELECT t.id, t.name, t.slug, COUNT(ae.id) AS total
            FROM analytics_events ae
            JOIN tenants t ON t.id = ae.tenant_id
            WHERE ae.created_at >= {$sinceSql}
            GROUP BY t.id, t.name, t.slug
            ORDER BY total DESC, t.name ASC
            LIMIT 50");
        $dayRows = $this->rows("SELECT DAYNAME(created_at) AS label, COUNT(*) AS total
            FROM analytics_events
            WHERE created_at >= {$sinceSql}
            GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
            ORDER BY DAYOFWEEK(created_at)");

        $cards = $this->summaryCards($totalEvents, $uniqueTenants, $imageViews, $days);
        $daysOptions = $this->daysOptions($days);
        $tenantTable = $this->tenantTable($tenantRows);
        $dayGraph = $this->barGraph($dayRows);

        $body = <<<HTML
<p class="admin-muted">Platform-wide analytics across all tenants. For tenant-specific detail, use the tenant domain admin route: <code>/admin/stats</code>.</p>
<form class="admin-filter-bar" method="get" action="/admin/stats">
    <label>Range<br><select name="days">{$daysOptions}</select></label>
    <button type="submit">Apply</button>
    <a href="/admin/stats">Clear</a>
</form>
{$cards}
<section class="admin-panel">
    <h2>Hits by day of week</h2>
    {$dayGraph}
</section>
<section class="admin-panel">
    <h2>Tenant activity</h2>
    {$tenantTable}
</section>
HTML;

        return Response::html(AdminLayout::render('Platform Stats', $body, 'stats'));
    }

    private function scalar(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        if (!$stmt) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    private function rows(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        if (!$stmt) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function summaryCards(int $events, int $tenants, int $imageViews, int $days): string
    {
        return '<div class="admin-stat-grid">'
            . $this->statCard('Events', $events, $days . ' days')
            . $this->statCard('Active tenants', $tenants, 'with analytics events')
            . $this->statCard('Artwork views', $imageViews, 'image_view events')
            . '</div>';
    }

    private function statCard(string $label, int $value, string $note): string
    {
        return '<article class="admin-stat-card"><strong>' . number_format($value) . '</strong><span>'
            . $this->escape($label) . '</span><small>' . $this->escape($note) . '</small></article>';
    }

    private function daysOptions(int $selected): string
    {
        $html = '';
        foreach ([7, 30, 90, 180, 365] as $days) {
            $isSelected = $selected === $days ? ' selected' : '';
            $html .= '<option value="' . $days . '"' . $isSelected . '>' . $days . ' days</option>';
        }

        return $html;
    }

    private function tenantTable(array $rows): string
    {
        if ($rows === []) {
            return '<p class="admin-muted">No analytics events found for this range.</p>';
        }

        $html = '<table class="admin-table"><thead><tr><th>Tenant</th><th>Slug</th><th>Events</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . $this->escape((string) $row['name']) . '</td><td>' . $this->escape((string) $row['slug'])
                . '</td><td>' . number_format((int) $row['total']) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    private function barGraph(array $rows): string
    {
        if ($rows === []) {
            return '<p class="admin-muted">No day-of-week data for this range.</p>';
        }

        $max = max(array_map(static fn (array $row): int => (int) $row['total'], $rows));
        $html = '<div class="admin-bar-graph">';
        foreach ($rows as $row) {
            $total = (int) $row['total'];
            $width = $max > 0 ? max(2, (int) round(($total / $max) * 100)) : 0;
            $html .= '<div class="admin-bar-row"><span>' . $this->escape((string) $row['label']) . '</span><div><i style="width:' . $width . '%"></i></div><strong>' . number_format($total) . '</strong></div>';
        }
        $html .= '</div>';

        return $html;
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
