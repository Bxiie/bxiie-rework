<?php

// Verifies that scale fixture seeding is isolated and cleanup-safe by construction.

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = (string) file_get_contents($root . '/scripts/dev/seed_scale_dataset.php');
$service = (string) file_get_contents($root . '/app/Platform/ScaleTesting/ScaleTenantFixtureService.php');
$combined = $script . "\n" . $service;

$required = [
    "public const MARKER_KEY = 'scale_dataset_marker';",
    "public const MARKER_VALUE = 'artsfolio-scale-fixture-v1';",
    "public const SLUG_PREFIX = 'scale-';",
    "JOIN tenant_settings s ON s.tenant_id = t.id",
    "s.setting_key = :marker_key",
    "s.setting_value = :marker_value",
    'Refusing to seed or cleanup scale data in a production-looking environment.',
    'removeScaleUploadDirectories',
    'new ScaleTenantFixtureService($pdo, $root)',
];

foreach ($required as $needle) {
    if (!str_contains($combined, $needle)) {
        fwrite(STDERR, "Scale fixture static check failed. Missing: {$needle}\n");
        exit(1);
    }
}

if (preg_match('/DELETE\s+FROM\s+tenants/i', $service)) {
    fwrite(STDERR, "Scale fixture cleanup must not use an unjoined DELETE FROM tenants.\n");
    exit(1);
}

echo "Scale fixture static checks passed.\n";

// End of file.
