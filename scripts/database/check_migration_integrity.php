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
        'settings',
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
        'email_signups',
    ],
    '0010_rate_limits.sql' => [
        'rate_limits',
    ],
];

$appliedStmt = $pdo->query("SELECT migration FROM schema_migrations");
$applied = array_fill_keys(array_map(
    static fn (array $row): string => (string) $row['migration'],
    $appliedStmt->fetchAll()
), true);

$problems = [];

foreach ($expected as $migration => $tables) {
    $isApplied = isset($applied[$migration]);

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
}

$result = [
    'ok' => count($problems) === 0,
    'problem_count' => count($problems),
    'problems' => $problems,
];

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

exit($result['ok'] ? 0 : 1);

// End of file.
