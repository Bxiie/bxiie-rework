<?php

declare(strict_types=1);

/**
 * Static coverage for no-reload persistent preview switch behavior.
 */

$root = dirname(__DIR__, 2);
$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';

if (!is_file($homePath)) {
    fwrite(STDERR, "Missing HomeController: {$homePath}\n");
    exit(1);
}

$source = file_get_contents($homePath);

$required = [
    'function unpublishedPreviewFooterSwitch(TenantContext $tenant): string',
    'function previewSwitchSuppressedForCurrentPath(): bool',
    'function unpublishedPreviewSwitchScript(): string',
    'data-preview-switch="1"',
    'data-preview-toggle',
    'data-preview-url',
    'fetch(toggleUrl',
    'document.body.replaceWith(parsed.body)',
    'window.__artsfolioPreviewSwitchReady',
    "in_array(\$path, ['/about', '/contact'], true)",
    'Preview preference is saved for your user on this tenant.',
    '$this->storedUnpublishedPreviewPreference($tenant)',
    '$this->canPreviewUnpublished($tenant)',
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing no-reload preview switch marker: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    '<a href="\' . $this->escape($href) . \'">\' . $this->escape($label) . \'</a>',
    '{$this->unpublishedPreviewFooterSwitch()}',
    '$this->unpublishedPreviewEnabled()',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "Forbidden stale preview switch behavior remains: {$needle}\n");
        exit(1);
    }
}

echo "No-reload persistent tenant preview switch checks passed.\n";

// End of file.
