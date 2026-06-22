<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'database/migrations/0042_tenant_directory_profiles.sql' => [
        'CREATE TABLE IF NOT EXISTS tenant_directory_profiles',
        'idx_tenant_directory_profiles_listed_sort',
        'ON DUPLICATE KEY UPDATE',
    ],
    'app/Platform/Directory/TenantDirectoryProfileRepository.php' => [
        'final class TenantDirectoryProfileRepository',
        'public function syncTenant(',
        'public function rebuildAll(',
        'public function page(',
        'public function listedCount(',
    ],
    'app/Http/Controllers/Platform/DirectoryController.php' => [
        'TenantDirectoryProfileRepository',
        'private const PER_PAGE = 24',
        '‹ Previous',
        'Next ›',
    ],
    'app/Tenant/Settings/TenantSettingsRepository.php' => [
        'platform_directory_opt_in',
        'TenantDirectoryProfileRepository',
    ],
    'app/Platform/Tenancy/TenantDomainRepository.php' => [
        'TenantDirectoryProfileRepository',
        'syncTenant(',
    ],
    'scripts/maintenance/rebuild_directory_profiles.php' => [
        'require $root . \'/bootstrap/app.php\'',
        'Database::connect($root)',
        'rebuildAll()',
        'tenants_synced',
    ],
];

foreach ($checks as $relative => $needles) {
    $text = file_get_contents($root . '/' . $relative);
    if ($text === false) {
        throw new RuntimeException("Missing {$relative}");
    }
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            throw new RuntimeException("Missing {$needle} in {$relative}");
        }
    }
}

$directory = file_get_contents($root . '/app/Http/Controllers/Platform/DirectoryController.php');
foreach (["JOIN tenant_settings", 'LIMIT 100', 'settingsTable()'] as $forbidden) {
    if (str_contains((string) $directory, $forbidden)) {
        throw new RuntimeException("Directory controller still contains legacy query fragment: {$forbidden}");
    }
}

echo "Phase 6 directory projection static checks passed.\n";

// End of file.
