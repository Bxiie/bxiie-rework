<?php

declare(strict_types=1);

/**
 * Static regression checks for tenant public typography settings.
 */

$root = dirname(__DIR__, 2);
$settingsController = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$homeController = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php');
$siteCss = file_get_contents($root . '/public/assets/site.css');
$tenantAdminCss = file_get_contents($root . '/public/assets/tenant-admin.css');
$preflight = file_get_contents($root . '/scripts/test/preflight.sh');

$failures = [];

$mustContain = [
    [$settingsController, '<legend>Typography</legend>', 'Settings page has Typography section'],
    [$settingsController, 'private function fontFamilyOptions(): array', 'Settings controller defines curated font picker choices'],
    [$settingsController, 'private function fontSelect(', 'Settings controller renders font picker selects'],
    [$settingsController, 'private function safeFontFamily(', 'Settings controller validates font family selections'],
    [$settingsController, 'private function safePublicFontSize(', 'Settings controller validates public font sizes'],
    [$settingsController, "fontSelect('font_family_body'", 'Body font picker rendered'],
    [$settingsController, "fontSelect('font_family_heading'", 'Heading font picker rendered'],
    [$settingsController, "fontSelect('font_family_brand'", 'Site title font picker rendered'],
    [$settingsController, "fontSelect('font_family_nav'", 'Navigation font picker rendered'],
    [$settingsController, "fontSelect('font_family_artwork_title'", 'Artwork title font picker rendered'],
    [$settingsController, "fontSelect('font_family_artwork_meta'", 'Artwork metadata font picker rendered'],
    [$settingsController, "fontSelect('font_family_form'", 'Form font picker rendered'],
    [$settingsController, "fontSelect('font_family_footer'", 'Footer font picker rendered'],
    [$settingsController, 'name="font_size_body"', 'Body font size rendered'],
    [$settingsController, 'name="font_size_heading"', 'Heading font size rendered'],
    [$settingsController, 'name="font_size_subheading"', 'Subheading font size rendered'],
    [$settingsController, 'name="font_size_brand"', 'Site title font size rendered'],
    [$settingsController, 'name="font_size_nav"', 'Navigation font size rendered'],
    [$settingsController, 'name="font_size_prose"', 'Intro/prose font size rendered'],
    [$settingsController, 'name="font_size_artwork_title"', 'Artwork title font size rendered'],
    [$settingsController, 'name="font_size_artwork_meta"', 'Artwork metadata font size rendered'],
    [$settingsController, 'name="font_size_form"', 'Form font size rendered'],
    [$settingsController, 'name="font_size_footer"', 'Footer font size rendered'],
    [$homeController, 'private function tenantTypographyCssVariables', 'Public layout emits typography CSS variables'],
    [$homeController, '--tenant-font-body', 'Public layout emits body font variable'],
    [$homeController, '--tenant-font-heading', 'Public layout emits heading font variable'],
    [$homeController, '--tenant-font-brand', 'Public layout emits site title font variable'],
    [$homeController, '--tenant-font-nav', 'Public layout emits navigation font variable'],
    [$homeController, '--tenant-font-artwork-title', 'Public layout emits artwork title font variable'],
    [$homeController, '--tenant-font-artwork-meta', 'Public layout emits artwork metadata font variable'],
    [$homeController, '--tenant-font-form', 'Public layout emits form font variable'],
    [$homeController, '--tenant-font-footer', 'Public layout emits footer font variable'],
    [$homeController, 'private function safeCssFontFamily', 'Public layout sanitizes font family variables'],
    [$siteCss, 'Tenant-configurable public typography', 'Public CSS documents tenant typography variables'],
    [$siteCss, 'var(--tenant-font-body', 'Public CSS uses body font variable'],
    [$siteCss, 'var(--tenant-font-heading', 'Public CSS uses heading font variable'],
    [$siteCss, 'var(--tenant-font-brand', 'Public CSS uses brand font variable'],
    [$siteCss, 'var(--tenant-font-nav', 'Public CSS uses nav font variable'],
    [$siteCss, 'var(--tenant-font-artwork-title', 'Public CSS uses artwork title font variable'],
    [$siteCss, 'var(--tenant-font-artwork-meta', 'Public CSS uses artwork metadata font variable'],
    [$siteCss, 'var(--tenant-font-form', 'Public CSS uses form font variable'],
    [$siteCss, 'var(--tenant-font-footer', 'Public CSS uses footer font variable'],
    [$tenantAdminCss, '.tenant-typography-grid', 'Tenant admin CSS styles typography controls'],
    [$tenantAdminCss, '.font-picker-preview', 'Tenant admin CSS styles font picker preview'],
    [$preflight, 'tenant_typography_settings_static.php', 'Preflight runs tenant typography settings static check'],
];

foreach ($mustContain as [$haystack, $needle, $label]) {
    if (!str_contains((string) $haystack, $needle)) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}

foreach (['font_family_', 'font_size_'] as $prefix) {
    if (!str_contains($settingsController, "str_starts_with(\$key, '" . $prefix . "')")) {
        $failures[] = 'Settings update path does not validate ' . $prefix . ' keys.';
    }
}

if (substr_count($settingsController, '<select class="tenant-font-picker"') < 1) {
    $failures[] = 'Font picker select markup is missing.';
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant typography settings static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Tenant typography settings static checks passed.\n";

// End of file.
