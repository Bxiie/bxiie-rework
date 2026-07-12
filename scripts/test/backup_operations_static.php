<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$required = [
    'scripts/backup/artsfolio_backup.sh' => ['mariadb-dump', 'restic backup', 'hourly.json'],
    'scripts/backup/artsfolio_backup_weekly_check.sh' => ['restic check', '--force-report', 'weekly.json'],
    'scripts/backup/artsfolio_backup_monthly_restore_test.sh' => ['restic restore latest', '--force-report', 'monthly.json'],
    'app/Platform/Monitoring/OperationsMonitor.php' => ['collectBackupMetrics', 'backup.snapshot.age_minutes', 'backup.restore_test.status'],
    'app/Http/Controllers/Platform/Admin/OperationsController.php' => ['Backup protection:', 'weekly integrity checks', 'monthly restore tests'],
    'docs/dev/backup-restore-cookbook.md' => ['Backblaze B2', 'System Operations', 'platform owners and administrators'],
];

$errors = [];
foreach ($required as $path => $markers) {
    $contents = @file_get_contents($root . '/' . $path);
    if (!is_string($contents)) {
        $errors[] = "Missing file: {$path}";
        continue;
    }
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = "Missing marker in {$path}: {$marker}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Backup operations static check failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "[PASS] Backup operations static check passed.\n";

// End of file.
