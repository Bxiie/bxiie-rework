<?php

declare(strict_types=1);

/**
 * Static coverage for schema-safe Platform Admin all-tenant search.
 */

$root = dirname(__DIR__, 2);
$tenantsPath = $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php';

if (!is_file($tenantsPath)) {
    fwrite(STDERR, "Missing TenantsController: {$tenantsPath}\n");
    exit(1);
}

$source = file_get_contents($tenantsPath);

$required = [
    [$source, 'function tenantSearchPanel', 'tenants controller must have search panel helper'],
    [$source, 'function tenantSearchTableExists', 'tenants controller must guard optional search tables'],
    [$source, 'function tenantSearchColumnExists', 'tenants controller must guard optional search columns'],
    [$source, '$this->tenantSearchPanel() .', 'tenants controller must prepend search panel to render body'],
    [$source, 'Search results across all tenants', 'tenant search must render all-tenant results'],
    [$source, 'tenantSearchColumnExists(\'tenants\', \'name\')', 'tenant name column must be guarded'],
    [$source, 'tenantSearchColumnExists(\'tenants\', \'uuid\')', 'tenant uuid column must be guarded'],
    [$source, 'tenantSearchColumnExists(\'tenants\', \'complementary\')', 'tenant complementary column must be guarded'],
    [$source, 'tenantSearchTableExists(\'tenant_plan_assignments\')', 'plan assignment join must be table-guarded'],
    [$source, 'tenantSearchTableExists(\'plans\')', 'plan join must be table-guarded'],
    [$source, 'LIMIT 250', 'tenant search must cap results'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    't.name,',
    't.uuid,',
    'COALESCE(t.complementary, 0) AS complementary,',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "Forbidden unguarded tenant search SQL fragment: {$needle}\n");
        exit(1);
    }
}

echo "Schema-safe Platform Admin tenant search static checks passed.\n";

// End of file.
