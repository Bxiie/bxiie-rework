<?php

declare(strict_types=1);

/**
 * Static coverage for per-user persistent tenant unpublished preview state.
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
    'function syncUnpublishedPreviewPreferenceFromQuery(TenantContext $tenant): void',
    'function storedUnpublishedPreviewPreference(TenantContext $tenant): bool',
    'function unpublishedPreviewPreferenceKey(): string',
    'function currentUserId(): int',
    'function canPreviewUnpublished(TenantContext $tenant): bool',
    'function unpublishedPreviewFooterSwitch(TenantContext $tenant): string',
    '$this->settings->set($tenant, $this->unpublishedPreviewPreferenceKey(), $raw);',
    '$this->settings->get($tenant, $this->unpublishedPreviewPreferenceKey(), \'0\') === \'1\'',
    '\'public_preview_unpublished_user_\' . $this->currentUserId()',
    '$previewSwitch = $this->unpublishedPreviewFooterSwitch($tenant);',
    '{$previewSwitch}',
    '$this->unpublishedPreviewEnabled($tenant)',
    'findPublishedBySlug($tenant, $slug, $this->unpublishedPreviewEnabled($tenant))',
    'preview_unpublished',
    'Preview preference is saved for your user on this tenant.',
    'Show unpublished sections and images',
    'Published-only view',
    'tenant-preview-switch',
    'r.slug IN (\'owner\', \'admin\')',
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing persistent preview marker: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    '$includeUnpublished = $this->currentUser !== null;',
    'findPublishedBySlug($tenant, $slug, $this->currentUser !== null)',
    '{$this->unpublishedPreviewFooterSwitch()}',
    '$this->unpublishedPreviewEnabled()',
    'return (string) ($_GET[\'preview_unpublished\'] ?? \'\') === \'1\';',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "Forbidden stale/non-persistent preview behavior remains: {$needle}\n");
        exit(1);
    }
}

echo "Per-user persistent tenant unpublished preview checks passed.\n";

// End of file.
