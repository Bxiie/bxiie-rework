<?php

declare(strict_types=1);

/**
 * Guards the Platform Admin Restic settings and runtime exporter integration.
 */

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Controllers/Platform/Admin/SettingsController.php' => [
        '<legend>Off-site backups</legend>',
        'name="restic_repository"',
        'name="restic_password"',
        'name="b2_account_id"',
        'name="b2_account_key"',
        'name="restic_weekly_read_subset"',
        "Configured; leave blank to keep",
        "if (\$resticPassword !== '')",
        "if (\$b2AccountId !== '')",
        "if (\$b2AccountKey !== '')",
    ],
    'scripts/backup/export_restic_environment.php' => [
        "'RESTIC_REPOSITORY'",
        "'RESTIC_PASSWORD'",
        "'B2_ACCOUNT_ID'",
        "'B2_ACCOUNT_KEY'",
        "'ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET'",
        'shellQuote($value)',
    ],
    'scripts/backup/artsfolio_backup.sh' => [
        'export_restic_environment.php',
        'mktemp /run/artsfolio-restic-env.',
    ],
    'scripts/backup/artsfolio_backup_weekly_check.sh' => [
        'export_restic_environment.php',
        'ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET',
    ],
    'scripts/backup/artsfolio_backup_monthly_restore_test.sh' => [
        'export_restic_environment.php',
    ],
];

$failures = [];
foreach ($checks as $relative => $markers) {
    $path = $root . '/' . $relative;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    if ($contents === '') {
        $failures[] = "Missing or empty file: {$relative}";
        continue;
    }
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $failures[] = "Missing marker in {$relative}: {$marker}";
        }
    }
}

foreach (glob($root . '/scripts/backup/*.sh') ?: [] as $path) {
    $contents = (string) file_get_contents($path);
    if (str_contains($contents, '/etc/artsfolio/backup.env')) {
        $failures[] = 'Legacy backup.env dependency remains in ' . basename($path);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Restic Platform Settings static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "[PASS] Restic Platform Settings static check passed.\n";

// End of file.
