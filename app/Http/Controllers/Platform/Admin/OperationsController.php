<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;
use App\Platform\Monitoring\OperationsMonitorRepository;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Operations\OperationsTaskLauncher;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;

final class OperationsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly OperationsMonitorRepository $operations,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
        private readonly OperationsTaskLauncher $launcher,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->allowed($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $start = trim((string) ($_GET['start'] ?? date('Y-m-d', strtotime('-7 days'))));
        $end = trim((string) ($_GET['end'] ?? date('Y-m-d')));
        $status = strtoupper(trim((string) ($_GET['status'] ?? '')));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $runs = $this->operations->searchRuns($start, $end, $status, $perPage, ($page - 1) * $perPage);
        $metrics = $this->operations->latestMetricRows();
        $runRows = '';
        foreach ($runs as $run) {
            $runRows .= '<tr><td><a href="/platform/admin/operations/runs/' . (int) $run['id'] . '">#' . (int) $run['id'] . '</a></td>'
                . '<td>' . $this->badge((string) $run['overall_status']) . '</td>'
                . '<td>' . AdminLayout::escape($this->displayUtcTime((string) $run['created_at'])) . '</td>'
                . '<td>' . (int) $run['critical_count'] . '</td><td>' . (int) $run['warning_count'] . '</td>'
                . '<td>' . number_format(((int) $run['duration_ms']) / 1000, 2) . 's</td></tr>';
        }
        if ($runRows === '') {
            $runRows = '<tr><td colspan="6">No saved monitor runs yet.</td></tr>';
        }

        $cards = '';
        foreach ($metrics as $metric) {
            $name = (string) $metric['metric_name'];
            $history = $this->operations->metricHistoryRange($name, $start, $end, 160);
            $url = '/platform/admin/operations/metrics?name=' . rawurlencode($name) . '&start=' . rawurlencode($start) . '&end=' . rawurlencode($end);
            $cards .= '<article class="ops-metric-card"><div class="ops-metric-head"><a href="' . AdminLayout::escape($url) . '"><strong>' . AdminLayout::escape($this->friendlyName($name)) . '</strong></a>'
                . $this->badge((string) $metric['metric_status']) . '</div>'
                . '<div class="ops-metric-value">' . AdminLayout::escape((string) $metric['actual_value']) . '</div>'
                . '<div class="ops-metric-expected">Expected: ' . AdminLayout::escape((string) $metric['expected_value']) . '</div>'
                . $this->sparkline($history)
                . '<div class="ops-metric-time">Updated ' . AdminLayout::escape($this->displayUtcTime((string) $metric['created_at'])) . '</div></article>';
        }
        if ($cards === '') {
            $cards = '<p>No saved metric rows yet. Run the monitor after applying migration 0045.</p>';
        }

        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $body = $this->styles()
            . '<div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem">'
            . '<form method="post" action="/platform/admin/operations/run-monitor">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<button type="submit">Run monitor now</button></form>'
            . '</div>'
            . '<form class="admin-form" method="get" action="/platform/admin/operations"><div class="admin-grid-2"><label>Trend/check start<input type="date" name="start" value="' . AdminLayout::escape($start) . '"></label><label>Trend/check end<input type="date" name="end" value="' . AdminLayout::escape($end) . '"></label><label>Check status<select name="status"><option value="">All</option><option value="OK"' . ($status==='OK'?' selected':'') . '>OK</option><option value="WARN"' . ($status==='WARN'?' selected':'') . '>WARN</option><option value="CRIT"' . ($status==='CRIT'?' selected':'') . '>CRIT</option></select></label></div><button type="submit">Apply range</button></form>'
            . '<p class="admin-muted">Trend lines cover ' . AdminLayout::escape($start) . ' through ' . AdminLayout::escape($end) . ' (' . AdminLayout::escape($this->displayTimezone()) . ').</p>'
            . '<div class="ops-summary-grid">' . $cards . '</div>'
            . '<h2>Recent system checks</h2><table class="admin-table"><thead><tr><th>Run</th><th>Status</th><th>Time</th><th>Critical</th><th>Warnings</th><th>Duration</th></tr></thead><tbody>' . $runRows . '</tbody></table>'
            . '<p><a class="admin-button" href="/platform/admin/operations?' . http_build_query(['start'=>$start,'end'=>$end,'status'=>$status,'page'=>max(1,$page-1)]) . '">Previous</a> <span class="admin-muted">Page ' . $page . '</span> <a class="admin-button" href="/platform/admin/operations?' . http_build_query(['start'=>$start,'end'=>$end,'status'=>$status,'page'=>$page+1]) . '">Next</a></p>';

        return Response::html(AdminLayout::render(title: 'System Operations', body: $body, active: 'operations'));
    }

    public function runMonitor(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform owner or administrator access required.'), 403);
        }
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
        }

        $result = $this->launcher->start('monitor');
        $this->auditLog->record(
            'platform.operations.monitor_manual_start',
            null,
            isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            'operations_task',
            'monitor',
            ['queued' => $result['ok'], 'exit_code' => $result['exit_code'], 'output' => mb_substr($result['output'], 0, 1000)],
            $request->server('REMOTE_ADDR'),
        );

        if ($result['ok']) {
            FlashMessages::success('The operations monitor was queued.');
        } else {
            FlashMessages::error('The operations monitor could not be queued: ' . $result['output']);
        }

        return new Response('', 302, ['Location' => '/platform/admin/operations']);
    }

    public function run(Request $request, ?array $currentUser, int $runId): Response
    {
        if (!$this->allowed($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        $run = $this->operations->run($runId);
        if ($run === null) {
            return Response::html(AdminLayout::render('System check not found', '<p>No saved system check exists with that ID.</p>', 'operations'), 404);
        }

        $runTimestamp = $this->utcTimestamp((string) ($run['created_at'] ?? '')) ?: time();
        $defaultEnd = date('Y-m-d', $runTimestamp);
        $defaultStart = date('Y-m-d', strtotime('-7 days', $runTimestamp));
        $start = trim((string) ($_GET['start'] ?? $defaultStart));
        $end = trim((string) ($_GET['end'] ?? $defaultEnd));

        $rows = '';
        foreach ($run['metrics'] as $metric) {
            $name = (string) $metric['metric_name'];
            $url = '/platform/admin/operations/metrics?name=' . rawurlencode($name) . '&start=' . rawurlencode($start) . '&end=' . rawurlencode($end);
            $rows .= '<tr><td>' . $this->badge((string) $metric['metric_status']) . '</td><td><a href="' . AdminLayout::escape($url) . '">' . AdminLayout::escape($this->friendlyName($name)) . '</a><br><code>' . AdminLayout::escape($name) . '</code></td>'
                . '<td>' . AdminLayout::escape((string) $metric['actual_value']) . '</td><td>' . AdminLayout::escape((string) $metric['expected_value']) . '</td><td>' . AdminLayout::escape((string) ($metric['detail_text'] ?? '')) . '</td></tr>';
        }
        $body = $this->styles() . '<p><a class="admin-button" href="/platform/admin/operations">Back to operations</a></p>'
            . '<dl><dt>Status</dt><dd>' . $this->badge((string) $run['overall_status']) . '</dd><dt>Host</dt><dd>' . AdminLayout::escape((string) $run['host_name']) . '</dd><dt>Checked</dt><dd>' . AdminLayout::escape($this->displayUtcTime((string) $run['created_at'])) . '</dd></dl>'
            . '<table class="admin-table"><thead><tr><th>Status</th><th>Check</th><th>Actual</th><th>Expected</th><th>Details</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        return Response::html(AdminLayout::render(title: 'System Check #' . $runId, body: $body, active: 'operations'));
    }

    public function metric(Request $request, ?array $currentUser): Response
    {
        if (!$this->allowed($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        $name = trim((string) ($_GET['name'] ?? ''));
        $start = trim((string) ($_GET['start'] ?? date('Y-m-d', strtotime('-7 days'))));
        $end = trim((string) ($_GET['end'] ?? date('Y-m-d')));
        if ($name === '') {
            return Response::html(AdminLayout::render('Metric missing', '<p>Select a metric from the operations dashboard.</p>', 'operations'), 422);
        }
        $history = $this->operations->metricHistoryRange($name, $start, $end, 1500);
        $rows = '';
        foreach (array_reverse($history) as $point) {
            $rows .= '<tr><td><a href="/platform/admin/operations/runs/' . (int) $point['run_id'] . '">#' . (int) $point['run_id'] . '</a></td><td>' . $this->badge((string) $point['metric_status']) . '</td><td>' . AdminLayout::escape((string) $point['actual_value']) . '</td><td>' . AdminLayout::escape($this->displayUtcTime((string) $point['created_at'])) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4">No history is available in this time window.</td></tr>';
        }
        $body = $this->styles() . '<p><a class="admin-button" href="/platform/admin/operations">Back to operations</a></p>'
            . '<form method="get" action="/platform/admin/operations/metrics"><input type="hidden" name="name" value="' . AdminLayout::escape($name) . '"><div class="admin-grid-2"><label>Start<input type="date" name="start" value="' . AdminLayout::escape($start) . '"></label><label>End<input type="date" name="end" value="' . AdminLayout::escape($end) . '"></label></div><button type="submit">Apply range</button></form>'
            . '<p class="admin-muted">Trend duration: ' . AdminLayout::escape($start) . ' through ' . AdminLayout::escape($end) . ' (' . AdminLayout::escape($this->displayTimezone()) . ').</p>'
            . '<p><code>' . AdminLayout::escape($name) . '</code></p><div class="ops-large-chart">' . $this->sparkline($history, 760, 210) . '</div>'
            . '<table class="admin-table"><thead><tr><th>Run</th><th>Status</th><th>Actual</th><th>Time</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        return Response::html(AdminLayout::render(title: $this->friendlyName($name), body: $body, active: 'operations'));
    }

    /**
     * Parses an operations timestamp as UTC and renders it in the active
     * administrator time zone.
     */
    private function displayUtcTime(string $raw): string
    {
        $timestamp = $this->utcTimestamp($raw);

        if ($timestamp === null) {
            return trim($raw);
        }

        return date('M j, Y g:i:s A T', $timestamp);
    }

    private function utcTimestamp(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $utc = new \DateTimeZone('UTC');
        $value = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw, $utc);

        if (!$value instanceof \DateTimeImmutable) {
            try {
                $value = new \DateTimeImmutable($raw, $utc);
            } catch (\Throwable) {
                return null;
            }
        }

        return $value->getTimestamp();
    }

    private function displayTimezone(): string
    {
        return (string) ($GLOBALS['artsfolio_user_timezone'] ?? date_default_timezone_get());
    }
    private function allowed(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT]);
    }

    private function badge(string $status): string
    {
        $class = strtolower($status);
        return '<span class="ops-badge ops-' . AdminLayout::escape($class) . '">' . AdminLayout::escape($status) . '</span>';
    }

    private function friendlyName(string $name): string
    {
        return ucwords(str_replace(['.', '_', '-'], ' ', $name));
    }

    private function sparkline(array $history, int $width = 260, int $height = 72): string
    {
        $numeric = array_values(array_filter($history, static fn(array $row): bool => $row['actual_numeric'] !== null));
        if (count($numeric) < 2) {
            $statusColors = ['CRIT' => '#a61b1b', 'WARN' => '#b36a00', 'OK' => '#278158', 'INFO' => '#607080'];
            $bars = '';
            $slice = array_slice($history, -30);
            foreach ($slice as $i => $row) {
                $x = 4 + $i * max(3, (int) (($width - 8) / max(1, count($slice))));
                $bars .= '<rect x="' . $x . '" y="18" width="3" height="' . max(8, $height - 28) . '" fill="' . ($statusColors[$row['metric_status']] ?? '#607080') . '" />';
            }
            return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Status history" style="width:100%;height:auto;">' . $bars . '</svg>';
        }
        $values = array_map(static fn(array $row): float => (float) $row['actual_numeric'], $numeric);
        $min = min($values); $max = max($values); $range = max(0.000001, $max - $min);
        $points = [];
        $count = count($values);
        foreach ($values as $i => $value) {
            $x = 5 + ($i / max(1, $count - 1)) * ($width - 10);
            $y = 5 + (($max - $value) / $range) * ($height - 16);
            $points[] = number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
        }
        return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Metric trend line" style="width:100%;height:auto;"><line x1="5" y1="' . ($height - 7) . '" x2="' . ($width - 5) . '" y2="' . ($height - 7) . '" stroke="#d8d2ca"/><polyline fill="none" stroke="#5c4db1" stroke-width="3" points="' . implode(' ', $points) . '" /></svg>';
    }

    private function styles(): string
    {
        return '<style>.ops-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin:18px 0 28px}.ops-metric-card{border:1px solid #ddd6cd;border-radius:12px;padding:14px;background:#fff}.ops-metric-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.ops-metric-value{font-size:22px;font-weight:750;margin:12px 0 3px}.ops-metric-expected,.ops-metric-time{color:#6f6961;font-size:13px}.ops-badge{display:inline-block;border-radius:999px;padding:4px 8px;font-size:12px;font-weight:800}.ops-crit{background:#f8d8d8;color:#8b1717}.ops-warn{background:#ffe8c2;color:#7b4800}.ops-ok{background:#d9f0e3;color:#175d3b}.ops-info{background:#e2e8ef;color:#3b4b5d}.ops-large-chart{max-width:900px;border:1px solid #ddd6cd;border-radius:12px;padding:10px;margin:18px 0;background:#fff}</style>';
    }
}

// End of file.
