<?php

declare(strict_types=1);

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Settings\TenantSettingsRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$host = $argv[1] ?? null;

if (!$host) {
    fwrite(STDERR, "Usage: php scripts/test/tenant_expected_title.php bxiie.com\n");
    exit(1);
}

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost($host);

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for {$host}\n");
    exit(1);
}

$settings = new TenantSettingsRepository($pdo);
$title = trim((string) $settings->get($tenant, 'site_title', $tenant->name));

if ($title === '') {
    fwrite(STDERR, "Resolved tenant {$host}, but configured site title is empty.\n");
    exit(1);
}

echo $title . PHP_EOL;

// End of file.
