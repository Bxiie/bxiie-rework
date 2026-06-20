<?php

declare(strict_types=1);

/**
 * Static regression checks for tenant color/background palette presets.
 */

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$script = file_get_contents($root . '/public/assets/admin-color-fields.js');
$css = file_get_contents($root . '/public/assets/tenant-admin.css');
$preflight = file_get_contents($root . '/scripts/test/preflight.sh');

$failures = [];

$mustContain = [
    [$controller, 'private function paletteButtons(): string', 'Settings controller renders palette buttons'],
    [$controller, 'private function palettes(): array', 'Settings controller defines palettes'],
    [$controller, "'name' => 'Default'", 'Default palette is first/available'],
    [$controller, "'name' => 'Gallery White'", 'Gallery White palette exists'],
    [$controller, "'name' => 'Ink Studio'", 'Ink Studio palette exists'],
    [$controller, "'name' => 'Desert Clay'", 'Desert Clay palette exists'],
    [$controller, "'name' => 'Forest Linen'", 'Forest Linen palette exists'],
    [$controller, "'name' => 'Signal Blue'", 'Signal Blue palette exists'],
    [$controller, "'name' => 'Rose Paper'", 'Rose Paper palette exists'],
    [$controller, "'name' => 'Concrete Pop'", 'Concrete Pop palette exists'],
    [$controller, 'data-tenant-palette', 'Palette buttons carry data payloads'],
    [$controller, '{$paletteButtons}', 'Palette buttons are rendered in the colors/background section'],
    [$controller, "'primary_color' =>", 'Palettes set primary color'],
    [$controller, "'background_color' =>", 'Palettes set page background color'],
    [$controller, "'menu_background_color' =>", 'Palettes set menu background color'],
    [$controller, "'background_opacity' =>", 'Palettes set background opacity'],

    [$controller, 'step="0.01"', 'Opacity fields accept hundredths so values like 0.72 are browser-valid'],
    [$script, "document.addEventListener('click'", 'Palette buttons use delegated click handling'],
    [$script, "event.target.closest('.tenant-palette-button[data-tenant-palette]')", 'Palette click handler works from nested swatch/name elements'],
    [$script, "if (document.readyState === 'loading')", 'Palette/color script boots even if loaded after DOMContentLoaded'],
    [$script, 'function applyNamedValue(name, value)', 'Palette application writes named form controls'],
    [$script, 'function applyPalette(button)', 'Admin color script applies palettes'],
    [$script, 'notifyFieldChanged(fields[0])', 'Palette application updates enhanced color fields'],
    [$css, '.tenant-palette-toolbar', 'Tenant admin CSS styles palette toolbar'],
    [$css, '.tenant-palette-button', 'Tenant admin CSS styles palette buttons'],
    [$css, '.tenant-palette-swatch', 'Tenant admin CSS styles palette swatches'],
    [$preflight, 'tenant_color_palettes_static.php', 'Preflight runs tenant color palette check'],
];

foreach ($mustContain as [$haystack, $needle, $label]) {
    if (!str_contains((string) $haystack, $needle)) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}

$paletteCount = substr_count($controller, "'name' =>");
if ($paletteCount !== 8) {
    $failures[] = 'Expected exactly 8 tenant palettes, found ' . $paletteCount . '.';
}

$defaultPosition = strpos($controller, "'name' => 'Default'");
$galleryPosition = strpos($controller, "'name' => 'Gallery White'");
if ($defaultPosition === false || $galleryPosition === false || $defaultPosition > $galleryPosition) {
    $failures[] = 'Default palette must be the first palette choice.';
}


if (substr_count($controller, 'step="0.01"') < 7) {
    $failures[] = 'Expected opacity inputs to use step="0.01" for hundredth values like 0.72.';
}

if (str_contains($controller, 'step="0.05"')) {
    $failures[] = 'Opacity inputs still contain step="0.05", which rejects values like 0.72.';
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant color palette static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Tenant color palette static checks passed.\n";

// End of file.
