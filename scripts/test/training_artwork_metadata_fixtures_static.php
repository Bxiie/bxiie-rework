<?php

declare(strict_types=1);

/**
 * Static regression checks for the training artwork metadata fixture.
 */

$root = dirname(__DIR__, 2);
$seeder = $root . '/scripts/training/seed_training_artwork_metadata.php';
$runner = $root . '/scripts/training/deploy_training_artwork_metadata.sh';

$errors = [];

foreach ([$seeder, $runner] as $path) {
    if (!is_file($path)) {
        $errors[] = "Missing file: {$path}";
    }
}

if ($errors === []) {
    $php = (string) file_get_contents($seeder);
    $shell = (string) file_get_contents($runner);

    $required = [
        "const TRAINING_SLUG = 'training';",
        "'variant_inventory'",
        "'limited_quantity'",
        "'one_off'",
        "'flat_per_order'",
        "'variant'",
        "'large_quote'",
        "'small_flat'",
        "'free_shipping'",
        "'clear_primary_media' => true",
        'applyCuration(',
        'backupRows(',
        '--apply',
        '--dry-run',
        'Site-type branding artworks remain unchanged.',
        'Homepage assignments are not changed',
    ];

    foreach ($required as $marker) {
        if (!str_contains($php, $marker) && !str_contains($shell, $marker)) {
            $errors[] = "Missing marker: {$marker}";
        }
    }

    $expectedSlugs = [
        'meridian-no-3',
        'folded-horizon',
        'counterweight',
        'red-shift',
        'quiet-vector',
        'field-notes-i',
        'field-notes-ii',
        'blue-interval',
        'small-orbit',
        'axis-study',
        'trial-assembly',
        'north-wall-proposal',
        'river-geometry',
        'untitled-maquette',
    ];

    $planStart = strpos($php, 'function artworkPlan(');
    $planEnd = strpos($php, 'function applyPlan(', $planStart === false ? 0 : $planStart);

    if ($planStart === false || $planEnd === false || $planEnd <= $planStart) {
        $errors[] = 'Unable to isolate artworkPlan() for fixture validation.';
    } else {
        $artworkPlanSource = substr($php, $planStart, $planEnd - $planStart);

        foreach ($expectedSlugs as $slug) {
            $marker = "'" . $slug . "' => [";
            $count = substr_count($artworkPlanSource, $marker);
            if ($count !== 1) {
                $errors[] = "Expected one artworkPlan entry for {$slug}; found {$count}.";
            }
        }
    }

    $forbidden = [
        'homepage_order',
        'DELETE FROM homepage_artwork_assignments',
        'INSERT INTO homepage_artwork_assignments',
        'Branding media asset; intentionally excluded',
        'tenant_id = 1894',
    ];

    foreach ($forbidden as $marker) {
        if (str_contains($php, $marker)) {
            $errors[] = "Forbidden marker remains: {$marker}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Training artwork metadata fixture static check failed:
");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}
");
    }
    exit(1);
}

echo "[PASS] Training artwork metadata fixture static check passed.
";

// End of file.
