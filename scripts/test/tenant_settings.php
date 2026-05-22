<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant settings read/write behavior.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Settings\TenantSettingsRepository;

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));

require $root . '/bootstrap/app.php';

$host = $argv[1] ?? 'settings-test.artsfol.io';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost($host);

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for {$host}\n");
    exit(1);
}

$settings = new TenantSettingsRepository($pdo);

$settings->set($tenant, 'site_title', 'Bxiie');
$settings->set($tenant, 'browser_title', 'Bxiie');
$settings->set($tenant, 'artist_name', 'Bxiie');

echo json_encode($settings->all($tenant), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
