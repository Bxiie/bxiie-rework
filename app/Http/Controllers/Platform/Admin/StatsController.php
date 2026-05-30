<?php

/**
 * Platform-wide analytics dashboard with bar charts and IP drill-down.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;
use PDO;

final class StatsController
{
    public function __construct(private readonly RequirePlatformRole $roles, private readonly PDO $pdo) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
        $since = "DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        $total = $this->scalar("SELECT COUNT(*) FROM analytics_events WHERE created_at >= {$since}");
        $tenants = $this->scalar("SELECT COUNT(DISTINCT tenant_id) FROM analytics_events WHERE created_at >= {$since}");
        $ips = $this->scalar("SELECT COUNT(DISTINCT COALESCE(NULLIF(ip_address, ''), ip_hash)) FROM analytics_events WHERE created_at >= {$since}");
        $tenantRows = $this->rows("SELECT CASE WHEN ae.tenant_id IS NULL THEN 'Platform pages' ELSE COALESCE(t.name, CONCAT('Tenant ', ae.tenant_id)) END AS label, COUNT(*) AS total FROM analytics_events ae LEFT JOIN tenants t ON t.id=ae.tenant_id WHERE ae.created_at >= {$since} GROUP BY label ORDER BY total DESC LIMIT 25");
        $dayRows = $this->rows("SELECT DAYNAME(created_at) AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY DAYOFWEEK(created_at), label ORDER BY DAYOFWEEK(created_at)");
        $hourRows = $this->rows("SELECT LPAD(HOUR(created_at), 2, '0') AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY label ORDER BY label");
        $locationRows = $this->rows("SELECT COALESCE(NULLIF(CONCAT_WS(', ', NULLIF(city, ''), NULLIF(region, ''), NULLIF(country, '')), ''), 'Unknown') AS label, country, region, city, COUNT(*) AS total, COUNT(DISTINCT COALESCE(NULLIF(ip_address, ''), ip_hash)) AS unique_ips FROM analytics_events WHERE created_at >= {$since} GROUP BY country, region, city ORDER BY total DESC LIMIT 25");
        $eventRows = $this->rows("SELECT event_type AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY event_type ORDER BY total DESC LIMIT 25");
        $platformPathRows = $this->rows("SELECT path AS label, COUNT(*) AS total FROM analytics_events WHERE tenant_id IS NULL AND created_at >= {$since} GROUP BY path ORDER BY total DESC LIMIT 25");
        $ipRows = $this->rows("SELECT COALESCE(NULLIF(ip_address, ''), CONCAT('hash:', LEFT(ip_hash, 16))) AS ip, COALESCE(NULLIF(CONCAT_WS(', ', NULLIF(city, ''), NULLIF(region, ''), NULLIF(country, '')), ''), 'Unknown') AS location, COUNT(*) AS total, MIN(created_at) AS first_seen, MAX(created_at) AS last_seen FROM analytics_events WHERE created_at >= {$since} GROUP BY ip, location ORDER BY total DESC, last_seen DESC LIMIT 250");

        $body = '<form class="admin-filter-bar" method="get"><label>Range days<br><input type="number" min="1" max="365" name="days" value="' . $days . '"></label><button>Apply</button></form>'
            . '<div class="admin-summary-grid"><div class="admin-summary-card"><strong>' . $total . '</strong><span>Total events</span></div><div class="admin-summary-card"><strong>' . $tenants . '</strong><span>Active tenants</span></div><button type="button" class="admin-summary-card stats-card-button" data-dialog="ip-dialog"><strong>' . $ips . '</strong><span>Unique IPs</span></button></div>'
            . '<section class="admin-panel"><h2>By tenant/platform</h2>' . $this->table($tenantRows, 'Tenant / Platform') . '</section>'
            . '<section class="admin-panel"><h2>Platform pages</h2>' . $this->table($platformPathRows, 'Path') . '</section>'
            . '<section class="admin-panel"><h2>By event type</h2>' . $this->table($eventRows, 'Event') . '</section>'
            . '<section class="admin-panel"><h2>By location</h2>' . $this->locationTable($locationRows) . '</section>'
            . '<section class="admin-panel"><h2>By day</h2>' . $this->barGraph($dayRows, 'Day') . $this->table($dayRows, 'Day') . '</section>'
            . '<section class="admin-panel"><h2>By hour</h2>' . $this->barGraph($hourRows, 'Hour') . $this->table($hourRows, 'Hour') . '</section>'
            . $this->ipDialog($ipRows)
            . $this->dialogScript();

        return Response::html(AdminLayout::render(title: 'Platform Stats', body: $body, active: 'stats'));
    }

    private function scalar(string $sql): int { return (int) $this->pdo->query($sql)->fetchColumn(); }
    private function rows(string $sql): array { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }

    private function table(array $rows, string $label): string
    {
        if (!$rows) { return '<p>No data for this range.</p>'; }
        $out = '<table class="admin-table"><thead><tr><th>' . AdminLayout::escape($label) . '</th><th>Total</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $out .= '<tr><td>' . AdminLayout::escape((string) $r['label']) . '</td><td>' . (int) $r['total'] . '</td></tr>';
        }
        return $out . '</tbody></table>';
    }

    private function locationTable(array $rows): string
    {
        if (!$rows) { return '<p>No data for this range.</p>'; }
        $out = '<table class="admin-table"><thead><tr><th>Location</th><th>Unique IPs</th><th>Total</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $out .= '<tr><td>' . AdminLayout::escape((string) $r['label']) . '</td><td><button type="button" class="link-button" data-dialog="ip-dialog">' . (int) $r['unique_ips'] . '</button></td><td>' . (int) $r['total'] . '</td></tr>';
        }
        return $out . '</tbody></table>';
    }

    private function barGraph(array $rows, string $label): string
    {
        if (!$rows) { return ''; }
        $max = max(array_map(static fn (array $r): int => max(1, (int) $r['total']), $rows));
        $out = '<div class="stats-bars" aria-label="' . AdminLayout::escape($label) . ' bar graph">';
        foreach ($rows as $r) {
            $height = max(6, (int) round(((int) $r['total'] / $max) * 120));
            $out .= '<div class="stats-bar-item"><div class="stats-bar-value">' . (int) $r['total'] . '</div><div class="stats-bar" style="height:' . $height . 'px"></div><div class="stats-bar-label">' . AdminLayout::escape((string) $r['label']) . '</div></div>';
        }
        return $out . '</div><style>.stats-bars{display:flex;align-items:end;gap:.5rem;min-height:170px;padding:1rem;border:1px solid #ddd;border-radius:12px;overflow:auto}.stats-bar-item{min-width:44px;text-align:center}.stats-bar{width:32px;margin:0 auto;background:currentColor;border-radius:8px 8px 0 0;opacity:.75}.stats-bar-label,.stats-bar-value{font-size:.78rem}.stats-card-button{text-align:left;cursor:pointer;border:0}.link-button{background:none;border:0;color:inherit;text-decoration:underline;cursor:pointer;padding:0}.stats-dialog{max-width:900px;width:90vw;border:0;border-radius:16px;padding:1rem;box-shadow:0 20px 80px rgba(0,0,0,.25)}.stats-dialog::backdrop{background:rgba(0,0,0,.35)}</style>';
    }

    private function ipDialog(array $rows): string
    {
        $trs = '';
        foreach ($rows as $r) {
            $trs .= '<tr><td><code>' . AdminLayout::escape((string) $r['ip']) . '</code></td><td>' . AdminLayout::escape((string) $r['location']) . '</td><td>' . (int) $r['total'] . '</td><td>' . AdminLayout::escape((string) $r['first_seen']) . '</td><td>' . AdminLayout::escape((string) $r['last_seen']) . '</td></tr>';
        }
        if ($trs === '') { $trs = '<tr><td colspan="5">No IP details for this range.</td></tr>'; }
        return '<dialog id="ip-dialog" class="stats-dialog"><form method="dialog" style="float:right"><button>Close</button></form><h2>Unique IP detail</h2><p class="admin-muted">Shows actual IP addresses for new analytics rows where available. Older rows show hash prefixes until new traffic is captured.</p><table class="admin-table"><thead><tr><th>IP</th><th>Location</th><th>Access count</th><th>First seen</th><th>Last seen</th></tr></thead><tbody>' . $trs . '</tbody></table></dialog>';
    }

    private function dialogScript(): string
    {
        return '<script>document.querySelectorAll("[data-dialog]").forEach(function(button){button.addEventListener("click",function(){var d=document.getElementById(button.getAttribute("data-dialog"));if(d&&d.showModal)d.showModal();});});</script>';
    }
}

// End of file.
