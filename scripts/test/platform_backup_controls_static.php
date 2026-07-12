<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$checks = [
    'app/Http/Controllers/Platform/Admin/BackupsController.php' => ['/platform/admin/backups/action', 'backup_action', 'Run backup now', 'Run verification now', 'Run restore test now'],
    'app/Platform/Operations/OperationsTaskLauncher.php' => ['/usr/local/sbin/artsfolio-admin-task', "'monitor'", "'backup'", "'integrity-check'", "'restore-test'", 'bypass_shell'],
    'app/Http/Controllers/Platform/Admin/OperationsController.php' => ['/platform/admin/operations/run-monitor', 'Run monitor now', 'platform.operations.monitor_manual_start'],
    'app/Http/Routes/platform.php' => ["post('/platform/admin/operations/run-monitor'", "get('/platform/admin/backups'", "post('/platform/admin/backups/action'"],
    'app/Http/View/AdminLayout.php' => ["'backups' => ['/platform/admin/backups', 'Backups']"],
    'scripts/ops/artsfolio-admin-task' => ['systemctl start --no-block artsfolio-monitor.service', 'systemctl start --no-block artsfolio-backup.service', 'systemctl start --no-block artsfolio-backup-weekly-check.service', 'systemctl start --no-block artsfolio-backup-monthly-restore.service'],
];
foreach ($checks as $relative => $markers) {
    $source = @file_get_contents($root . '/' . $relative);
    if (!is_string($source)) {
        $failures[] = "Missing readable file: {$relative}";
        continue;
    }
    foreach ($markers as $marker) {
        if (!str_contains($source, $marker)) {
            $failures[] = "{$relative} missing marker: {$marker}";
        }
    }
}
if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Platform backup controls static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}
echo "[PASS] Platform backup controls static check passed.\n";

// End of file.
