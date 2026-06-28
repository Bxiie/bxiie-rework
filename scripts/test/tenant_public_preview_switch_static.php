<?php

declare(strict_types=1);

/**
 * Static coverage for tenant public unpublished preview switch.
 */

$root = dirname(__DIR__, 2);
$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';

if (!is_file($homePath)) {
    fwrite(STDERR, "Missing HomeController: {$homePath}\n");
    exit(1);
}

$source = file_get_contents($homePath);

$required = [
    'function unpublishedPreviewEnabled(TenantContext $tenant): bool',
    'function canPreviewUnpublished(TenantContext $tenant): bool',
    'function unpublishedPreviewFooterSwitch(TenantContext $tenant): string',
    '$previewSwitch = $this->unpublishedPreviewFooterSwitch($tenant);',
    '{$previewSwitch}',
    '$this->unpublishedPreviewEnabled($tenant)',
    'findPublishedBySlug($tenant, $slug, $this->unpublishedPreviewEnabled($tenant))',
    'preview_unpublished',
    'Show unpublished sections and images',
    'Published-only view',
    'tenant-preview-switch',
    "r.slug IN ('owner', 'admin')",
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing tenant preview switch marker: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    '$includeUnpublished = $this->currentUser !== null;',
    'findPublishedBySlug($tenant, $slug, $this->currentUser !== null)',
    '{$this->unpublishedPreviewFooterSwitch()}',
    '$this->unpublishedPreviewEnabled()',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "Forbidden stale tenant preview behavior remains: {$needle}\n");
        exit(1);
    }
}

echo "Tenant public unpublished preview switch static checks passed.\n";

// End of file.
