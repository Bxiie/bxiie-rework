<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Platform/Monitoring/OperationsMonitor.php' => [
        'server.memory.used_percent',
        'database.connections.used_percent',
        'application.tenants.total',
        'queue.jobs.oldest_ready_age_minutes',
        'network.tls.',
        'private function defaultRoute()',
        '/sbin/route -n get default',
        'ARTSFOLIO_MONITOR_EXPECTED_WORKERS',
    ],
    'app/Platform/Monitoring/OperationsMonitorNotifier.php' => [
        '[ArtsFolio CRITICAL]',
        '[ArtsFolio WARNING]',
        'DryRunEmailSender',
    ],
    'app/Platform/Monitoring/HealthReport.php' => [
        'metricsBySeverity',
        'HealthMetric::CRIT => 0',
    ],
    'app/Platform/Email/DryRunEmailSender.php' => [
        'isset($email[\'id\'])',
    ],
    'app/Platform/Monitoring/OperationsMonitorRepository.php' => [
        'SELECT LAST_INSERT_ID()',
    ],
    'scripts/ops/monitor_artsfolio.php' => [
        '07:15',
        '19:15',
        'ARTSFOLIO_MONITOR_WARN_REMINDER_MINUTES',
        'ARTSFOLIO_MONITOR_CRIT_REMINDER_MINUTES',
        'force-report',
        'Health email dry run generated',
    ],
    'scripts/systemd/artsfolio-monitor.timer' => [
        'OnCalendar=*-*-* *:0/5:00',
        'Persistent=true',
    ],
    'database/migrations/0044_operations_monitoring_and_migration_checksums.sql' => [
        'checksum_sha256',
        'operations_monitor_runs',
        'operations_monitor_state',
    ],
    'database/migrations/0045_operations_monitor_metrics.sql' => [
        'operations_monitor_metrics',
        'last_boot_id',
    ],
];

$errors = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    if ($content === '') {
        $errors[] = "Missing file: {$relative}";
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "Missing {$needle} in {$relative}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "Phase 9 monitoring static checks passed.\n";

