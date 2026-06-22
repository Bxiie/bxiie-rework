<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Platform/Analytics/AnalyticsRollupService.php');
$script = file_get_contents($root . '/scripts/maintenance/rebuild_analytics_rollups.php');

$requiredServiceFragments = [
    'rebuildHourlyBucket',
    'rebuildDailyBucket',
    'WHERE created_at >= :event_start',
    'AND created_at < :event_end',
    'beginTransaction()',
    'CHAR(31)',
];
foreach ($requiredServiceFragments as $fragment) {
    if (!str_contains((string) $service, $fragment)) {
        fwrite(STDERR, "Missing bucketed-rollup fragment: {$fragment}\n");
        exit(1);
    }
}

$forbiddenServiceFragments = [
    'DATE_SUB(CURRENT_DATE',
    'GROUP BY 1,2,3,4,5,6,7,8,9',
    "DELETE FROM analytics_rollups_hourly WHERE bucket_start >=",
];
foreach ($forbiddenServiceFragments as $fragment) {
    if (str_contains((string) $service, $fragment)) {
        fwrite(STDERR, "Found unbounded rollup fragment: {$fragment}\n");
        exit(1);
    }
}

foreach (['--days', '--from', '--to', 'rebuildRange'] as $fragment) {
    if (!str_contains((string) $script, $fragment)) {
        fwrite(STDERR, "Missing maintenance option fragment: {$fragment}\n");
        exit(1);
    }
}

echo "Phase 3 rollup bucketing static checks passed.\n";

// End of file.
