<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant settings repository behavior used by tenant admin.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Settings\TenantSettingsRepository;

$envFile = (string) (getenv('ARTSFOLIO_ENV_FILE') ?: '');

if (str_contains($envFile, '/etc/artsfolio/')) {
    echo "Skipping tenant_settings_admin.php against production env.\n";
    exit(0);
}

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost('settings-test.artsfol.io');

if (!$tenant) {
    echo "Skipping tenant_settings_admin.php; optional settings-test tenant is not present.\n";
    exit(0);
}

$settings = new TenantSettingsRepository($pdo);

$settings->set($tenant, 'site_title', 'Bxiie Test Title');
$settings->set($tenant, 'site_admin_email', 'tenant-admin@example.test');

echo json_encode([
    'tenant_id' => $tenant->tenantId,
    'site_title' => $settings->get($tenant, 'site_title'),
    'site_admin_email' => $settings->get($tenant, 'site_admin_email'),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
