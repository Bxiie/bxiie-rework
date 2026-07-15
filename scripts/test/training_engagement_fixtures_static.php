<?php

/**
 * Verify that the Git-deployable training engagement fixture tooling retains
 * its safety boundaries and expected deterministic records.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$seedPath = $root . '/scripts/training/seed_training_engagement.php';
$rollbackPath = $root . '/scripts/training/rollback_training_engagement.php';
$deployPath = $root . '/scripts/training/deploy_training_engagement.sh';

$failures = [];

foreach ([$seedPath, $rollbackPath, $deployPath] as $path) {
    if (!is_file($path)) {
        $failures[] = "Missing required file: {$path}";
    }
}

if ($failures === []) {
    $seed = (string) file_get_contents($seedPath);
    $rollback = (string) file_get_contents($rollbackPath);
    $deploy = (string) file_get_contents($deployPath);

    $requiredSeedMarkers = [
        "const TRAINING_SLUG = 'training';",
        'Expected exactly one tenant with slug',
        'backupTrainingRows',
        'Northstar: Recent Sculpture',
        'training-buyer+taylor@example.com',
        'training-list+pending@example.com',
        'verifyCounts',
    ];

    foreach ($requiredSeedMarkers as $marker) {
        if (!str_contains($seed, $marker)) {
            $failures[] = "Seed script missing marker: {$marker}";
        }
    }

    foreach (['Northstar: Recent Sculpture', 'training-buyer+taylor@example.com', 'training-list+pending@example.com'] as $marker) {
        if (!str_contains($rollback, $marker)) {
            $failures[] = "Rollback script missing marker: {$marker}";
        }
    }

    if (!str_contains($deploy, 'ARTSFOLIO_ENV_FILE=')) {
        $failures[] = 'Deploy wrapper does not pass ARTSFOLIO_ENV_FILE.';
    }

    if (str_contains($deploy, 'mysql ') || str_contains($deploy, 'mysqldump')) {
        $failures[] = 'Deploy wrapper must not depend on mysql or mysqldump clients.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Training engagement fixture static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Training engagement fixture static check passed.\n";

// End of file.
