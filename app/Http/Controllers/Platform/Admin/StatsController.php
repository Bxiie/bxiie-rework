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
 * Platform-wide analytics dashboard with graph and location drilldown views.
 */
final class StatsController
{
    public function __construct(private readonly RequirePlatformRole $roles, private readonly PDO $pdo) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
        $location = trim((string) ($_GET['location'] ?? ''));
        $since = "DATE_SUB(NOW(), INTERVAL {$days} DAY)";

        $total = $this->scalar("SELECT COUNT(*) FROM analytics_events WHERE created_at >= {$since}");
        $tenants = $this->scalar("SELECT COUNT(DISTINCT tenant_id) FROM analytics_events WHERE created_at >= {$since}");
        $ips = $this->scalar("SELECT COUNT(DISTINCT ip_hash) FROM analytics_events WHERE created_at >= {$since}");
        $tenantRows = $this->rows("SELECT CASE WHEN ae.tenant_id IS NULL THEN 'Platform pages' ELSE COALESCE(t.name, CONCAT('Tenant ', ae.tenant_id)) END AS label, COUNT(*) AS total FROM analytics_events ae LEFT JOIN tenants t ON t.id=ae.tenant_id WHERE ae.created_at >= {$since} GROUP BY label ORDER BY total DESC LIMIT 25");
        $dayRows = $this->rows("SELECT DAYNAME(created_at) AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY DAYOFWEEK(created_at), label ORDER BY DAYOFWEEK(created_at)");
        $hourRows = $this->rows("SELECT LPAD(HOUR(created_at), 2, '0') AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY label ORDER BY label");
        $locationRows = $this->rows("SELECT CONCAT_WS(', ', NULLIF(city, ''), NULLIF(region, ''), NULLIF(country, '')) AS label, COUNT(DISTINCT ip_hash) AS unique_ips, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} AND (COALESCE(country, '') <> '' OR COALESCE(region, '') <> '' OR COALESCE(city, '') <> '') GROUP BY country, region, city ORDER BY total DESC LIMIT 50");
        $eventRows = $this->rows("SELECT event_type AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY event_type ORDER BY total DESC LIMIT 25");
        $platformPathRows = $this->rows("SELECT path AS label, COUNT(*) AS total FROM analytics_events WHERE tenant_id IS NULL AND created_at >= {$since} GROUP BY path ORDER BY total DESC LIMIT 25");
        $drilldown = $location !== '' ? $this->locationDrilldown($location, $since, $days) : '';

        $body = '<form class="admin-filter-bar" method="get"><label>Range days<br><input type="number" min="1" max="365" name="days" value="'.$days.'"></label><button>Apply</button></form>'
            . '<div class="admin-summary-grid"><div class="admin-summary-card"><strong>'.$total.'</strong><span>Total events</span></div><div class="admin-summary-card"><strong>'.$tenants.'</strong><span>Active tenants</span></div><div class="admin-summary-card"><strong>'.$ips.'</strong><span>Unique IP hashes</span></div></div>'
            . '<section class="admin-panel"><h2>Hits by day of week</h2>'.$this->barGraph($dayRows).'</section>'
            . '<section class="admin-panel"><h2>Hits by hour of day</h2>'.$this->barGraph($hourRows).'</section>'
            . '<section class="admin-panel"><h2>By tenant/platform</h2>'.$this->table($tenantRows, 'Tenant / Platform').'</section>'
            . '<section class="admin-panel"><h2>Platform pages</h2>'.$this->table($platformPathRows, 'Path').'</section>'
            . '<section class="admin-panel"><h2>By event type</h2>'.$this->table($eventRows, 'Event').'</section>'
            . '<section class="admin-panel"><h2>By location</h2>'.$this->locationTable($locationRows, $days).'</section>'
            . $drilldown;

        return Response::html(AdminLayout::render(title: 'Platform Stats', body: $body, active: 'stats'));
    }

    private function locationDrilldown(string $location, string $since, int $days): string
    {
        $rows = $this->rows("SELECT COALESCE(ip_hash, '') AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} AND CONCAT_WS(', ', NULLIF(city, ''), NULLIF(region, ''), NULLIF(country, '')) = " . $this->pdo->quote($location) . " GROUP BY ip_hash ORDER BY total DESC LIMIT 200");
        $safe = AdminLayout::escape($location);
        $back = '/platform/admin/stats?days=' . $days;

        return '<section class="admin-panel"><h2>Location drilldown: '.$safe.'</h2><p><a href="'.$back.'">Clear location drilldown</a></p>'.$this->table($rows, 'IP hash').'</section>';
    }

    private function scalar(string $sql): int { return (int) $this->pdo->query($sql)->fetchColumn(); }
    private function rows(string $sql): array { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []; }

    private function table(array $rows, string $label): string
    {
        if (!$rows) {
            return '<p>No data for this range.</p>';
        }
        $out = '<table class="admin-table"><thead><tr><th>'.$label.'</th><th>Total</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $out .= '<tr><td>'.AdminLayout::escape((string) $r['label']).'</td><td>'.(int) $r['total'].'</td></tr>';
        }

        return $out.'</tbody></table>';
    }

    private function locationTable(array $rows, int $days): string
    {
        if (!$rows) {
            return '<p>No data for this range.</p>';
        }
        $out = '<table class="admin-table"><thead><tr><th>Location</th><th>Unique IPs</th><th>Total</th><th>Drilldown</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $label = (string) $r['label'];
            $href = '/platform/admin/stats?days=' . $days . '&location=' . rawurlencode($label);
            $out .= '<tr><td>'.AdminLayout::escape($label).'</td><td>'.(int) $r['unique_ips'].'</td><td>'.(int) $r['total'].'</td><td><a href="'.AdminLayout::escape($href).'">Unique IPs</a></td></tr>';
        }

        return $out.'</tbody></table>';
    }

    private function barGraph(array $rows): string
    {
        if (!$rows) {
            return '<p>No data for this range.</p>';
        }
        $max = max(array_map(static fn (array $row): int => (int) $row['total'], $rows));
        $html = '<div class="admin-bar-chart">';
        foreach ($rows as $row) {
            $total = (int) $row['total'];
            $width = $max > 0 ? max(2, (int) round(($total / $max) * 100)) : 0;
            $label = AdminLayout::escape((string) $row['label']);
            $html .= '<div class="admin-bar-row"><span>'.$label.'</span><span class="admin-bar-track"><span class="admin-bar-fill" style="width:'.$width.'%"></span></span><strong>'.$total.'</strong></div>';
        }

        return $html.'</div>';
    }
}

// End of file.
