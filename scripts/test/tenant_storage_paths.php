<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant-isolated storage path generation.
 */

use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantStoragePaths;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$tenant = new TenantContext(
    tenantId: 1,
    tenantUuid: 'test-uuid',
    slug: 'bxiie',
    name: 'Bxiie',
    hostname: 'bxiie.com',
    domainType: 'custom',
    isPrimaryDomain: true,
);

$paths = new TenantStoragePaths($tenant);

$expected = [
    'tenants/bxiie/originals/test.jpg' => $paths->originalsPath('../test.jpg'),
    'tenants/bxiie/derivatives/thumb.webp' => $paths->derivativesPath('thumb.webp'),
    'tenants/bxiie/imports/import.csv' => $paths->importsPath('import.csv'),
    'tenants/bxiie/private/export.zip' => $paths->privatePath('export.zip'),
];

foreach ($expected as $want => $got) {
    if ($want !== $got) {
        fwrite(STDERR, "Path mismatch. Wanted {$want}, got {$got}\n");
        exit(1);
    }
}

echo "Tenant storage path verification passed.\n";

// End of file.
