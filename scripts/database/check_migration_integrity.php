<?php

declare(strict_types=1);

/**
 * Checks migration ledger consistency against expected tables introduced by known migrations.
 */

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$expected = [
    '0001_platform_core.sql' => [
        'schema_migrations',
        'tenants',
        'tenant_domains',
        'tenant_settings',
        'plans',
        'users',
        'audit_log',
    ],
    '0002_content_media.sql' => [
        'media_assets',
        'artworks',
        'portfolio_sections',
    ],
    '0003_background_jobs.sql' => [
        'background_jobs',
    ],
    '0004_domain_artifacts.sql' => [
        'domain_artifacts',
    ],
    '0005_identity_membership.sql' => [
        'user_identities',
        'tenant_memberships',
        'roles',
        'role_assignments',
        'password_reset_tokens',
        'email_verification_tokens',
    ],
    '0006_user_sessions.sql' => [
        'user_sessions',
    ],
    '0007_oauth_tokens.sql' => [
        'oauth_clients',
        'oauth_access_tokens',
        'oauth_refresh_tokens',
    ],
    '0008_email_outbox.sql' => [
        'email_outbox',
    ],
    '0009_contact_signup_records.sql' => [
        'contact_messages',
    ],
    '0010_rate_limits.sql' => [
        'rate_limits',
    ],
    '0011_repair_email_signups.sql' => [
        'email_signups',
    ],
    '0038_media_asset_variants.sql' => [
        'media_asset_variants',
    ],
    '0042_tenant_directory_profiles.sql' => [
        'tenant_directory_profiles',
    ],
    '0043_sales_inventory_reservations.sql' => [
        'sales_inventory_reservations',
    ],
    '0044_operations_monitoring_and_migration_checksums.sql' => [
        'operations_monitor_runs',
        'operations_monitor_state',
    ],
    '0045_operations_monitor_metrics.sql' => [
        'tables' => ['operations_monitor_metrics'],
        'columns' => [
            'operations_monitor_state' => ['last_boot_id'],
        ],
    ],
];

$appliedStmt = $pdo->query("SELECT migration FROM schema_migrations");
$applied = array_fill_keys(array_map(
    static fn (array $row): string => (string) $row['migration'],
    $appliedStmt->fetchAll()
), true);

$problems = [];


$migrationFiles = [];
foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
    $migrationFiles[basename($file)] = $file;
}

foreach (array_keys($applied) as $migration) {
    if (!isset($migrationFiles[$migration])) {
        $problems[] = [
            'migration' => $migration,
            'problem' => 'applied_migration_file_missing',
        ];
    }
}

foreach (array_keys($migrationFiles) as $migration) {
    if (!isset($applied[$migration])) {
        $problems[] = [
            'migration' => $migration,
            'problem' => 'migration_file_not_applied',
        ];
    }
}

$checksumColumnStmt = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'schema_migrations'
       AND column_name = 'checksum_sha256'"
);
$checksumColumnExists = (int) $checksumColumnStmt->fetchColumn() > 0;

if ($checksumColumnExists) {
    $checksumRows = $pdo->query('SELECT migration, checksum_sha256 FROM schema_migrations')->fetchAll();
    foreach ($checksumRows as $row) {
        $migration = (string) $row['migration'];
        $recorded = (string) ($row['checksum_sha256'] ?? '');
        if (!isset($migrationFiles[$migration])) {
            continue;
        }
        $actual = hash_file('sha256', $migrationFiles[$migration]);
        if ($recorded === '' || !hash_equals($actual, $recorded)) {
            $problems[] = [
                'migration' => $migration,
                'problem' => $recorded === '' ? 'migration_checksum_missing' : 'migration_checksum_mismatch',
            ];
        }
    }
}

foreach ($expected as $migration => $requirements) {
    $isApplied = isset($applied[$migration]);

    // Historical entries are simple table lists. Newer entries may declare
    // tables and columns separately so a column is never mistaken for a table.
    $tables = array_is_list($requirements)
        ? $requirements
        : ($requirements['tables'] ?? []);
    $columns = array_is_list($requirements)
        ? []
        : ($requirements['columns'] ?? []);

    foreach ($tables as $table) {
        $existsStmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
        $existsStmt->execute(['table_name' => $table]);
        $exists = (bool) $existsStmt->fetch();

        if ($isApplied && !$exists) {
            $problems[] = [
                'migration' => $migration,
                'table' => $table,
                'problem' => 'migration_recorded_but_table_missing',
            ];
        }

        if (!$isApplied && $exists) {
            $problems[] = [
                'migration' => $migration,
                'table' => $table,
                'problem' => 'table_exists_but_migration_not_recorded',
            ];
        }
    }

    foreach ($columns as $table => $columnNames) {
        foreach ($columnNames as $column) {
            $columnStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name
                   AND column_name = :column_name"
            );
            $columnStmt->execute([
                'table_name' => $table,
                'column_name' => $column,
            ]);
            $exists = (int) $columnStmt->fetchColumn() > 0;

            if ($isApplied && !$exists) {
                $problems[] = [
                    'migration' => $migration,
                    'table' => $table,
                    'column' => $column,
                    'problem' => 'migration_recorded_but_column_missing',
                ];
            }

            if (!$isApplied && $exists) {
                $problems[] = [
                    'migration' => $migration,
                    'table' => $table,
                    'column' => $column,
                    'problem' => 'column_exists_but_migration_not_recorded',
                ];
            }
        }
    }
}

$result = [
    'ok' => count($problems) === 0,
    'problem_count' => count($problems),
    'problems' => $problems,
];

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

exit($result['ok'] ? 0 : 1);

// End of file.
