<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant domain repository behavior.
 */

use App\Platform\Tenancy\TenantDomainRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$host = $argv[1] ?? 'bxiie.com';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost($host);

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for {$host}\n");
    exit(1);
}

$domains = new TenantDomainRepository($pdo);

$domains->addDomain(
    tenantId: $tenant->tenantId,
    hostname: 'test-domain.example',
    domainType: 'custom',
    status: 'pending_dns',
    isPrimary: false,
);

$domains->setStatus('test-domain.example', 'dns_verified');

echo json_encode($domains->listForTenant($tenant->tenantId), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
