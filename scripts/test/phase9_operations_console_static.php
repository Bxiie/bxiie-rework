<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'migration' => ['database/migrations/0045_operations_monitor_metrics.sql', ['operations_monitor_metrics', 'last_boot_id']],
    'controller' => ['app/Http/Controllers/Platform/Admin/OperationsController.php', ['/platform/admin/operations/runs/', 'metricHistory', 'ops-summary-grid', 'sparkline']],
    'routes' => ['app/Http/Routes/platform.php', ['/platform/admin/operations', '/platform/admin/operations/metrics', '/platform/admin/operations/runs/{id}']],
    'report' => ['app/Platform/Monitoring/HealthReport.php', ['toHtml', 'Critical issues requiring attention', 'Open operations dashboard']],
    'monitor' => ['scripts/ops/monitor_artsfolio.php', ['artsfolioCurrentBootId', 'restartDetected', "'restart'"]],
];
foreach ($checks as $label => [$relative, $needles]) {
    $text = file_get_contents($root . '/' . $relative);
    if ($text === false) { fwrite(STDERR, "Missing {$label}: {$relative}\n"); exit(1); }
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) { fwrite(STDERR, "Missing {$label} check: {$needle}\n"); exit(1); }
    }
}
echo "Phase 9 operations console static checks passed.\n";
