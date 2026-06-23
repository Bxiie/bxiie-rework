<?php

declare(strict_types=1);

/**
 * Static regression checks for tenant color/background palette presets.
 */

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$contentController = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/ContentController.php');
$script = file_get_contents($root . '/public/assets/admin-color-fields.js');
$css = file_get_contents($root . '/public/assets/tenant-admin.css');
$siteCss = file_get_contents($root . '/public/assets/site.css');
$preflight = file_get_contents($root . '/scripts/test/preflight.sh');
$tenantLayout = file_get_contents($root . '/app/Http/View/TenantAdminLayout.php');
$platformLayout = file_get_contents($root . '/app/Http/View/AdminLayout.php');
$homeController = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php');

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
    [$controller, "'name' => 'Midnight Olive'", 'Midnight Olive palette exists'],
    [$controller, "'name' => 'Ultraviolet Paper'", 'Ultraviolet Paper palette exists'],
    [$controller, 'data-tenant-palette', 'Palette buttons carry data payloads'],
    [$controller, 'data-palette-tone', 'Palette buttons expose mood/temperature labels'],

    [$controller, "'topbar_text_color' =>", 'Palettes set top bar text color for contrast'],
    [$controller, "'menu_text_color' =>", 'Palettes set menu text color for contrast'],
    [$controller, 'name="topbar_text_color"', 'Settings form exposes top bar text color editing'],
    [$controller, 'name="menu_text_color"', 'Settings form exposes menu text color editing'],
    [$controller, '--palette-button-topbar', 'Palette buttons preview top bar color'],
    [$controller, '--palette-button-menu', 'Palette buttons preview menu color'],
    [$controller, 'tenant-palette-preview', 'Palette buttons include a visual palette preview'],
    [$homeController, '--tenant-topbar-text', 'Public tenant layout exposes top bar text variable'],
    [$homeController, '--menu-text-color', 'Public tenant layout exposes menu text variable'],
    [$tenantLayout, '--tenant-topbar-text', 'Tenant admin layout exposes top bar text variable'],
    [$tenantLayout, '--menu-text-color', 'Tenant admin layout exposes menu text variable'],
    [$script, "fieldValue('topbar_text_color')", 'Live preview reads top bar text color'],
    [$script, "fieldValue('menu_text_color')", 'Live preview reads menu text color'],
    [$script, "setRootVariable('--tenant-topbar-text'", 'Live preview updates top bar text color'],
    [$script, "setRootVariable('--menu-text-color'", 'Live preview updates menu text color'],
    [$css, '.tenant-palette-preview', 'Tenant admin CSS styles palette preview'],
    [$css, '.tenant-admin-panel .tenant-palette-button', 'Palette button CSS outranks generic tenant admin buttons'],
    [$tenantLayout, 'tenant-admin.css?v=20260623-sidebar-upload-palette', 'Tenant admin CSS cache busts palette button styling'],
    [$platformLayout, 'tenant-admin.css?v=20260623-sidebar-upload-palette', 'Platform admin CSS cache busts palette button styling'],
    [$siteCss, '--tenant-topbar-text', 'Public CSS uses top bar text variable'],
    [$siteCss, '--menu-text-color', 'Public CSS uses menu text variable'],
    [$controller, '--palette-button-bg', 'Palette buttons carry mood background color'],
    [$controller, '--palette-button-accent', 'Palette buttons carry mood accent color'],
    [$controller, '{$paletteButtons}', 'Palette buttons are rendered in the colors/background section'],
    [$controller, "'primary_color' =>", 'Palettes set primary color'],
    [$controller, "'background_color' =>", 'Palettes set page background color'],
    [$controller, "'menu_background_color' =>", 'Palettes set menu background color'],
    [$controller, "'background_opacity' =>", 'Palettes set background opacity'],
    [$homeController, "--topbar-bg-overlay", 'Public tenant layout exposes topbar overlay CSS variable'],
    [$tenantLayout, "--topbar-bg-overlay", 'Tenant admin layout exposes topbar overlay CSS variable'],
    [$homeController, "--tenant-topbar-bg", 'Public tenant layout exposes tenant topbar background variable'],
    [$tenantLayout, "--tenant-topbar-bg", 'Tenant admin layout exposes tenant topbar background variable'],

    [$controller, 'step="0.01"', 'Opacity fields accept hundredths so values like 0.72 are browser-valid'],
    [$script, "document.addEventListener('click'", 'Palette buttons use delegated click handling'],
    [$script, 'function findPaletteButton(target)', 'Palette click handler does not depend on Element.closest support'],
    [$script, 'window.ArtsFolioApplyTenantPalette = applyPalette', 'Palette applier is exposed for browser-console diagnostics'],
    [$script, "if (document.readyState === 'loading')", 'Palette/color script boots even if loaded after DOMContentLoaded'],
    [$script, 'function applyNamedValue(name, value)', 'Palette application writes named form controls'],
    [$script, 'function applyPalette(button)', 'Admin color script applies palettes'],
    [$script, 'notifyFieldChanged(fields[0])', 'Palette application updates enhanced color fields'],
    [$script, 'function syncTenantPreviewVariables()', 'Palette changes update tenant admin preview CSS variables'],
    [$script, "setRootVariable('--topbar-bg-overlay'", 'Palette changes update top bar overlay color live'],
    [$script, "setRootVariable('--tenant-topbar-bg'", 'Palette changes update tenant admin top bar color live'],
    [$script, "setRootVariable('--menu-bg-overlay'", 'Palette changes update menu panel color live'],
    [$css, '.tenant-palette-toolbar', 'Tenant admin CSS styles palette toolbar'],
    [$css, '.tenant-palette-button', 'Tenant admin CSS styles palette buttons'],
    [$css, '.tenant-palette-swatch', 'Tenant admin CSS styles palette swatches'],
    [$css, 'var(--palette-button-bg', 'Tenant admin CSS colors palette buttons by mood'],
    [$css, 'content: attr(data-palette-tone)', 'Tenant admin CSS displays palette mood/temperature'],
    [$preflight, 'tenant_color_palettes_static.php', 'Preflight runs tenant color palette check'],
    [$tenantLayout, '/assets/admin-color-fields.js?v=20260620-palette-contrast', 'Tenant admin layout cache-busts palette JavaScript'],
    [$platformLayout, '/assets/admin-color-fields.js?v=20260620-palette-contrast', 'Platform admin layout cache-busts shared color JavaScript'],
];


function extract_method_body(string $source, string $methodName): string
{
    $needle = 'function ' . $methodName . '(';
    $start = strpos($source, $needle);
    if ($start === false) {
        return '';
    }

    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        return '';
    }

    $depth = 0;
    $length = strlen($source);
    for ($i = $brace; $i < $length; $i++) {
        $char = $source[$i];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $brace + 1, $i - $brace - 1);
            }
        }
    }

    return '';
}

$paletteSource = extract_method_body($controller, 'palettes');
if ($paletteSource === '') {
    $failures[] = 'Could not locate SettingsController::palettes() for color palette checks.';
    $paletteSource = $controller;
}

foreach ($mustContain as [$haystack, $needle, $label]) {
    if (!str_contains((string) $haystack, $needle)) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}

$paletteCount = substr_count($paletteSource, "'name' =>");
if ($paletteCount !== 10) {
    $failures[] = 'Expected exactly 10 tenant palettes, found ' . $paletteCount . '.';
}

$defaultPosition = strpos($paletteSource, "'name' => 'Default'");
$galleryPosition = strpos($paletteSource, "'name' => 'Gallery White'");
if ($defaultPosition === false || $galleryPosition === false || $defaultPosition > $galleryPosition) {
    $failures[] = 'Default palette must be the first palette choice.';
}


if (substr_count($controller, 'step="0.01"') < 7) {
    $failures[] = 'Expected opacity inputs to use step="0.01" for hundredth values like 0.72.';
}

if (str_contains($controller, 'step="0.05"') || str_contains($contentController, 'step="0.05"')) {
    $failures[] = 'Opacity inputs still contain step="0.05", which rejects values like 0.72.';
}

foreach (['watermark_opacity', 'about_image_opacity', 'contact_image_opacity'] as $opacityField) {
    $source = $opacityField === 'watermark_opacity' ? $controller : $contentController;
    if (!preg_match('/name="' . preg_quote($opacityField, '/') . '"[^>]*step="0\\.01"/', $source)) {
        $failures[] = 'Opacity field does not accept hundredth values: ' . $opacityField;
    }
}

if (substr_count($paletteSource, "'tone' =>") !== 10) {
    $failures[] = 'Expected exactly 10 palette tone labels.';
}

if (substr_count($paletteSource, "'topbar_text_color' =>") !== 10 || substr_count($paletteSource, "'menu_text_color' =>") !== 10) {
    $failures[] = 'Expected exactly 10 topbar/menu text contrast colors.';
}

if (substr_count($paletteSource, "'button_background' =>") !== 10
    || substr_count($paletteSource, "'button_text' =>") !== 10
    || substr_count($paletteSource, "'button_accent' =>") !== 10) {
    $failures[] = 'Expected exactly 10 palette button mood color sets.';
}

if (substr_count($tenantLayout, 'admin-color-fields.js') !== 1) {
    $failures[] = 'Tenant admin layout should include admin-color-fields.js exactly once.';
}

if (substr_count($platformLayout, 'admin-color-fields.js') !== 1) {
    $failures[] = 'Platform admin layout should include admin-color-fields.js exactly once.';
}


function hex_luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return 0.0;
    }

    $parts = [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)];
    $channels = array_map(static function (string $part): float {
        $value = hexdec($part) / 255;
        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, $parts);

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrast_ratio(string $a, string $b): float
{
    $l1 = hex_luminance($a);
    $l2 = hex_luminance($b);
    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);

    return ($lighter + 0.05) / ($darker + 0.05);
}

$paletteBlocks = [];
if (preg_match_all("/'name' => '([^']+)',.*?'values' => \[([^\]]+)\]/s", $paletteSource, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $values = [];
        if (preg_match_all("/'([^']+)' => '([^']+)'/", $match[2], $valueMatches, PREG_SET_ORDER)) {
            foreach ($valueMatches as $valueMatch) {
                $values[$valueMatch[1]] = $valueMatch[2];
            }
        }
        $paletteBlocks[$match[1]] = $values;
    }
}

foreach ($paletteBlocks as $paletteName => $values) {
    foreach ([['topbar_background_color', 'topbar_text_color', 'top bar'], ['menu_background_color', 'menu_text_color', 'menu']] as [$bgKey, $textKey, $surface]) {
        $bg = $values[$bgKey] ?? '';
        $text = $values[$textKey] ?? '';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $bg) || !preg_match('/^#[0-9a-fA-F]{6}$/', $text)) {
            $failures[] = $paletteName . ' palette has non-hex ' . $surface . ' contrast values.';
            continue;
        }

        if (contrast_ratio($bg, $text) < 4.5) {
            $failures[] = $paletteName . ' palette ' . $surface . ' contrast is below 4.5:1.';
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant color palette static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Tenant color palette static checks passed.\n";

// End of file.
