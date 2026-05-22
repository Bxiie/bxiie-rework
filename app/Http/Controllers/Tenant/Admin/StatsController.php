<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Tenant-scoped usage statistics dashboard.
 */
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
        $imageSearch = trim((string) ($_GET['q'] ?? ''));
        $sinceSql = "DATE_SUB(NOW(), INTERVAL {$days} DAY)";

        $totalEvents = $this->scalar("SELECT COUNT(*) FROM analytics_events WHERE tenant_id = :tenant_id AND created_at >= {$sinceSql}", ['tenant_id' => $tenant->tenantId]);
        $uniqueIps = $this->scalar("SELECT COUNT(DISTINCT ip_hash) FROM analytics_events WHERE tenant_id = :tenant_id AND created_at >= {$sinceSql}", ['tenant_id' => $tenant->tenantId]);
        $imageViews = $this->scalar("SELECT COUNT(*) FROM analytics_events WHERE tenant_id = :tenant_id AND event_type = 'image_view' AND created_at >= {$sinceSql}", ['tenant_id' => $tenant->tenantId]);

        $dayRows = $this->rows("SELECT DAYOFWEEK(created_at) AS bucket, DAYNAME(created_at) AS label, COUNT(*) AS total
            FROM analytics_events
            WHERE tenant_id = :tenant_id AND created_at >= {$sinceSql}
            GROUP BY bucket, label
            ORDER BY bucket", ['tenant_id' => $tenant->tenantId]);

        $hourRows = $this->rows("SELECT HOUR(created_at) AS bucket, COUNT(*) AS total
            FROM analytics_events
            WHERE tenant_id = :tenant_id AND created_at >= {$sinceSql}
            GROUP BY bucket
            ORDER BY bucket", ['tenant_id' => $tenant->tenantId]);

        $locationRows = $this->rows("SELECT COALESCE(country, '') AS country, COALESCE(region, '') AS state, COALESCE(city, '') AS city, COUNT(*) AS total
            FROM analytics_events
            WHERE tenant_id = :tenant_id AND created_at >= {$sinceSql}
            GROUP BY country, region, city
            ORDER BY total DESC
            LIMIT 50", ['tenant_id' => $tenant->tenantId]);

        $imageParams = ['tenant_id' => $tenant->tenantId];
        $imageWhere = "ae.tenant_id = :tenant_id AND ae.event_type = 'image_view' AND ae.created_at >= {$sinceSql}";
        if ($imageSearch !== '') {
            $imageWhere .= " AND a.title LIKE :q";
            $imageParams['q'] = '%' . $imageSearch . '%';
        }

        $imageRows = $this->rows("SELECT a.id, a.title, a.slug, m.uuid AS media_uuid, COUNT(*) AS total
            FROM analytics_events ae
            LEFT JOIN artworks a ON a.id = ae.entity_id AND a.tenant_id = ae.tenant_id
            LEFT JOIN media_assets m ON m.id = a.primary_media_id
            WHERE {$imageWhere}
            GROUP BY a.id, a.title, a.slug, m.uuid
            ORDER BY total DESC
            LIMIT 100", $imageParams);

        $daysOptions = $this->daysOptions($days);
        $q = $this->escape($imageSearch);
        $summary = $this->summaryCards((int) $totalEvents, (int) $uniqueIps, (int) $imageViews, $days);
        $dayGraph = $this->barGraph($dayRows, 'label');
        $hourGraph = $this->barGraph($this->normalizeHours($hourRows), 'label');
        $locations = $this->locationTable($locationRows);
        $images = $this->imageTable($imageRows);

        $body = <<<HTML
<p><a href="/admin">&larr; Admin</a></p>
<form class="admin-filter-bar" method="get" action="/admin/stats">
    <label>Range<br>
        <select name="days">{$daysOptions}</select>
    </label>
    <label>Image search<br>
        <input type="search" name="q" value="{$q}" placeholder="Artwork title">
    </label>
    <button type="submit">Apply</button>
    <a href="/admin/stats">Clear</a>
</form>

{$summary}

<section class="admin-panel">
    <h2>Hits by day of week</h2>
    {$dayGraph}
</section>

<section class="admin-panel">
    <h2>Hits by hour of day</h2>
    {$hourGraph}
</section>

<section class="admin-panel">
    <h2>Top artwork views</h2>
    {$images}
</section>

<section class="admin-panel">
    <h2>Locations</h2>
    <p class="admin-muted">Location rows appear when analytics events have city, state/region, or country fields populated.</p>
    {$locations}
</section>
HTML;

        return Response::html(AdminLayout::render('Stats', $body));
    }

    private function scalar(string $sql, array $params): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function summaryCards(int $totalEvents, int $uniqueIps, int $imageViews, int $days): string
    {
        return <<<HTML
<div class="admin-summary-grid">
    <div class="admin-summary-card"><strong>{$totalEvents}</strong><span>Total events, {$days} days</span></div>
    <div class="admin-summary-card"><strong>{$uniqueIps}</strong><span>Unique IP hashes</span></div>
    <div class="admin-summary-card"><strong>{$imageViews}</strong><span>Artwork views</span></div>
</div>
HTML;
    }

    private function barGraph(array $rows, string $labelKey): string
    {
        if (!$rows) {
            return '<p>No data for this range.</p>';
        }

        $max = max(array_map(static fn (array $row): int => (int) $row['total'], $rows)) ?: 1;
        $html = '<div class="stats-graph">';

        foreach ($rows as $row) {
            $label = $this->escape((string) $row[$labelKey]);
            $total = (int) $row['total'];
            $width = max(2, (int) round(($total / $max) * 100));
            $html .= "<div class=\"stats-bar-row\"><span>{$label}</span><span class=\"stats-bar-track\"><span class=\"stats-bar-fill\" style=\"width:{$width}%\"></span></span><strong>{$total}</strong></div>";
        }

        return $html . '</div>';
    }

    private function normalizeHours(array $rows): array
    {
        $byHour = [];
        foreach ($rows as $row) {
            $byHour[(int) $row['bucket']] = (int) $row['total'];
        }

        $normalized = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $normalized[] = [
                'label' => sprintf('%02d:00', $hour),
                'total' => $byHour[$hour] ?? 0,
            ];
        }

        return $normalized;
    }

    private function imageTable(array $rows): string
    {
        if (!$rows) {
            return '<p>No matching image views.</p>';
        }

        $html = '<table class="admin-table"><thead><tr><th>Image</th><th>Artwork</th><th>Views</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $title = $this->escape((string) ($row['title'] ?? 'Unknown artwork'));
            $slug = rawurlencode((string) ($row['slug'] ?? ''));
            $total = (int) $row['total'];
            $image = '';
            if (!empty($row['media_uuid'])) {
                $src = '/media?uuid=' . rawurlencode((string) $row['media_uuid']);
                $image = '<img class="admin-thumb" src="' . $src . '" alt="' . $title . '">';
            }
            $link = $slug !== '' ? '<a href="/artwork/' . $slug . '">' . $title . '</a>' : $title;
            $html .= "<tr><td>{$image}</td><td>{$link}</td><td>{$total}</td></tr>";
        }

        return $html . '</tbody></table>';
    }

    private function locationTable(array $rows): string
    {
        if (!$rows) {
            return '<p>No location data yet.</p>';
        }

        $html = '<table class="admin-table"><thead><tr><th>City</th><th>State/Region</th><th>Country</th><th>Hits</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $city = $this->escape((string) $row['city']);
            $state = $this->escape((string) $row['state']);
            $country = $this->escape((string) $row['country']);
            $total = (int) $row['total'];
            $html .= "<tr><td>{$city}</td><td>{$state}</td><td>{$country}</td><td>{$total}</td></tr>";
        }

        return $html . '</tbody></table>';
    }

    private function daysOptions(int $current): string
    {
        $options = [7 => '7 days', 30 => '30 days', 90 => '90 days', 180 => '180 days', 365 => '365 days'];
        $html = '';
        foreach ($options as $value => $label) {
            $selected = $value === $current ? ' selected' : '';
            $html .= "<option value=\"{$value}\"{$selected}>{$label}</option>";
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
