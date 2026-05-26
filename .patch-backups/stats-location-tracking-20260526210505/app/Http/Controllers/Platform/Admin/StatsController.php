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
 * Platform-wide analytics dashboard.
 */
final class StatsController
{
    public function __construct(private readonly RequirePlatformRole $roles, private readonly PDO $pdo) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        $days=max(1,min(365,(int)($_GET['days']??30))); $since="DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        $total=$this->scalar("SELECT COUNT(*) FROM analytics_events WHERE created_at >= {$since}");
        $tenants=$this->scalar("SELECT COUNT(DISTINCT tenant_id) FROM analytics_events WHERE created_at >= {$since}");
        $ips=$this->scalar("SELECT COUNT(DISTINCT ip_hash) FROM analytics_events WHERE created_at >= {$since}");
        $tenantRows=$this->rows("SELECT COALESCE(t.name, CONCAT('Tenant ', ae.tenant_id)) AS label, COUNT(*) AS total FROM analytics_events ae LEFT JOIN tenants t ON t.id=ae.tenant_id WHERE ae.created_at >= {$since} GROUP BY label ORDER BY total DESC LIMIT 25");
        $dayRows=$this->rows("SELECT DAYNAME(created_at) AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY DAYOFWEEK(created_at), label ORDER BY DAYOFWEEK(created_at)");
        $hourRows=$this->rows("SELECT HOUR(created_at) AS label, COUNT(*) AS total FROM analytics_events WHERE created_at >= {$since} GROUP BY label ORDER BY label");
        $body='<form class="admin-filter-bar" method="get"><label>Range days<br><input type="number" min="1" max="365" name="days" value="'.$days.'"></label><button>Apply</button></form>'
            .'<div class="admin-summary-grid"><div class="admin-summary-card"><strong>'.$total.'</strong><span>Total events</span></div><div class="admin-summary-card"><strong>'.$tenants.'</strong><span>Active tenants</span></div><div class="admin-summary-card"><strong>'.$ips.'</strong><span>Unique IP hashes</span></div></div>'
            .'<section class="admin-panel"><h2>By tenant</h2>'.$this->table($tenantRows,'Tenant').'</section><section class="admin-panel"><h2>By day</h2>'.$this->table($dayRows,'Day').'</section><section class="admin-panel"><h2>By hour</h2>'.$this->table($hourRows,'Hour').'</section>';
        return Response::html(AdminLayout::render(title:'Platform Stats', body:$body, active:'stats'));
    }

    private function scalar(string $sql): int { return (int)$this->pdo->query($sql)->fetchColumn(); }
    private function rows(string $sql): array { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    private function table(array $rows,string $label): string { if(!$rows) return '<p>No data for this range.</p>'; $out='<table class="admin-table"><thead><tr><th>'.$label.'</th><th>Total</th></tr></thead><tbody>'; foreach($rows as $r){$out.='<tr><td>'.AdminLayout::escape((string)$r['label']).'</td><td>'.(int)$r['total'].'</td></tr>'; } return $out.'</tbody></table>'; }
}

// End of file.
