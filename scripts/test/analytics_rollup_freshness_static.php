<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/app/Platform/Monitoring/OperationsMonitor.php';
$source = file_get_contents($path);
if ($source === false) {
    throw new RuntimeException('Unable to read OperationsMonitor.php');
}

$required = [
    "MAX(completed_at) FROM background_jobs WHERE job_type = 'analytics.rollup' AND status = 'complete'",
    'TIMESTAMPDIFF(MINUTE, MAX(completed_at), CURRENT_TIMESTAMP)',
    "'no successful rollup job'",
    "' database time'",
];
foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException('Missing analytics rollup freshness assertion: ' . $needle);
    }
}

$forbidden = [
    'MAX(bucket_start) FROM analytics_rollups_hourly',
    "strtotime((string) \$latestRollup . ' UTC')",
];
foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        throw new RuntimeException('Obsolete analytics freshness logic remains: ' . $needle);
    }
}

echo "Analytics rollup completion freshness static checks passed.\n";
