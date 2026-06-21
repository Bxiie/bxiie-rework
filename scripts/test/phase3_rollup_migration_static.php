<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = file_get_contents($root . '/database/migrations/0039_analytics_rollups.sql');
$service = file_get_contents($root . '/app/Platform/Analytics/AnalyticsRollupService.php');

$failures = [];
foreach ([
    'DROP TABLE IF EXISTS analytics_rollups_daily',
    'CREATE TABLE analytics_rollups_daily',
    'bucket_date DATE NOT NULL',
    'dimension_hash CHAR(64) NOT NULL',
] as $needle) {
    if (!str_contains((string) $migration, $needle)) {
        $failures[] = "Migration missing: {$needle}";
    }
}
if (str_contains((string) $migration, 'CREATE TABLE IF NOT EXISTS analytics_rollups_daily LIKE')) {
    $failures[] = 'Migration must not derive the daily table with CREATE TABLE LIKE.';
}
if (str_contains((string) $migration, 'CHANGE bucket_start bucket_date')) {
    $failures[] = 'Migration must not rename bucket_start during a rerunnable partial migration.';
}
if (!str_contains((string) $service, 'dimension_hash')) {
    $failures[] = 'Rollup service does not populate dimension_hash.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Phase 3 rollup migration static checks passed.\n";

// End of file.
