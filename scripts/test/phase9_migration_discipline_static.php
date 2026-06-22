<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'scripts/database/migrate.php' => [
        'checksum_sha256',
        "hash_file('sha256'",
        'Migration checksum mismatch',
    ],
    'scripts/database/check_migration_integrity.php' => [
        'applied_migration_file_missing',
        'migration_file_not_applied',
        'migration_checksum_mismatch',
    ],
    'scripts/database/check_schema_health.php' => [
        'operations_monitor_runs',
        'sales_inventory_reservations',
        'tenant_directory_profiles',
    ],
    'scripts/test/migration_numbering_static.php' => [
        '$prefix >= 38',
        'Duplicate migration prefix',
        'Missing modern migration prefixes',
    ],
];

$errors = [];
foreach ($checks as $relative => $needles) {
    $content = @file_get_contents($root . '/' . $relative);
    if (!is_string($content)) {
        $errors[] = "Missing file: {$relative}";
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "Missing {$needle} in {$relative}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}


$integritySource = file_get_contents($root . '/scripts/database/check_migration_integrity.php');
if ($integritySource === false) {
    throw new RuntimeException('Unable to read migration integrity checker.');
}

foreach ([
    "'tables' => ['operations_monitor_metrics']",
    "'operations_monitor_state' => ['last_boot_id']",
    'migration_recorded_but_column_missing',
    'column_exists_but_migration_not_recorded',
] as $needle) {
    if (!str_contains($integritySource, $needle)) {
        throw new RuntimeException('Migration integrity checker missing column-aware assertion: ' . $needle);
    }
}

if (preg_match("/'0045_operations_monitor_metrics\.sql'\s*=>\s*\[\s*'operations_monitor_metrics',\s*'last_boot_id'/s", $integritySource) === 1) {
    throw new RuntimeException('last_boot_id must not be treated as a table.');
}

echo "Phase 9 migration discipline static checks passed.\n";
