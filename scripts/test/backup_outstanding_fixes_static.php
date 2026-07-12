<?php

declare(strict_types=1);

/**
 * Regression checks for the July 2026 backup reliability repair.
 */

$root = dirname(__DIR__, 2);
$failures = [];

/**
 * @param string $relativePath
 */
function source(string $root, string $relativePath, array &$failures): string
{
    $path = $root . '/' . $relativePath;
    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        $failures[] = "Missing readable file: {$relativePath}";
        return '';
    }
    return $contents;
}

$hourly = source($root, 'scripts/backup/artsfolio_backup.sh', $failures);
$weekly = source($root, 'scripts/backup/artsfolio_backup_weekly_check.sh', $failures);
$monthly = source($root, 'scripts/backup/artsfolio_backup_monthly_restore_test.sh', $failures);
$monitor = source($root, 'scripts/ops/monitor_artsfolio.php', $failures);
$unit = source($root, 'scripts/systemd/artsfolio-backup.service', $failures);

$requiredHourly = [
    'RESTIC_CACHE_DIR',
    '"$STAGING_DIR"',
    '--json |',
    'select(.message_type == "summary")',
    'snapshot_id_full',
];

foreach ($requiredHourly as $marker) {
    if (!str_contains($hourly, $marker)) {
        $failures[] = "Hourly backup missing marker: {$marker}";
    }
}

if (str_contains($hourly, 'restic snapshots --latest 1')) {
    $failures[] = 'Hourly backup must not infer the new snapshot from snapshots --latest 1.';
}

if (!str_contains($monthly, "grep 'CREATE TABLE' >/dev/null")) {
    $failures[] = 'Monthly restore must consume the complete gzip stream.';
}
if (str_contains($monthly, "grep -q 'CREATE TABLE'")) {
    $failures[] = 'Monthly restore still contains the pipefail/grep -q defect.';
}

foreach ([$weekly, $monthly] as $script) {
    if (!str_contains($script, 'runuser -u "$MONITOR_USER"')) {
        $failures[] = 'Scheduled verification job does not run the monitor as the artsfolio account.';
    }
    if (!str_contains($script, 'monitor_status > 2')) {
        $failures[] = 'Scheduled verification job does not distinguish health status from report failure.';
    }
}

if (!str_contains($monitor, 'Unable to open ArtsFolio monitor lock')) {
    $failures[] = 'Monitor does not report lock-open failures accurately.';
}
if (!str_contains($monitor, 'exit(75)')) {
    $failures[] = 'Monitor does not distinguish an actively held lock.';
}

if (str_contains($unit, 'EnvironmentFile=-/etc/artsfolio/backup.env')) {
    $failures[] = 'Obsolete /etc/artsfolio/backup.env dependency remains in the service.';
}
if (!str_contains($unit, 'CacheDirectory=artsfolio/restic')) {
    $failures[] = 'Backup service does not provision a writable Restic cache.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Backup outstanding-fixes static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Backup outstanding-fixes static check passed.\n";

// End of file.
