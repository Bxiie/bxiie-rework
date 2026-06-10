<?php

declare(strict_types=1);

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$host = $argv[1] ?? null;

if (!$host) {
    fwrite(STDERR, "Usage: php scripts/test/resolve_tenant.php bxiie.com\n");
    exit(1);
}

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);

$tenant = $resolver->resolveFromHost($host);

if (!$tenant) {
    echo "No tenant resolved for {$host}\n";
    exit(0);
}

echo json_encode([
    'tenant_id' => $tenant->tenantId,
    'tenant_uuid' => $tenant->tenantUuid,
    'slug' => $tenant->slug,
    'name' => $tenant->name,
    'hostname' => $tenant->hostname,
    'domain_type' => $tenant->domainType,
    'is_primary_domain' => $tenant->isPrimaryDomain,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
