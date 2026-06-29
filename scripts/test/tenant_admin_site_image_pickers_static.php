<?php

declare(strict_types=1);

/**
 * Static coverage for site-image thumbnail pickers in tenant admin.
 */

$root = dirname(__DIR__, 2);
$settingsPath = $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php';
$contentPath = $root . '/app/Http/Controllers/Tenant/Admin/ContentController.php';
$cssPath = $root . '/public/assets/tenant-admin.css';

foreach ([$settingsPath, $contentPath, $cssPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
}

$settings = file_get_contents($settingsPath);
$content = file_get_contents($contentPath);
$css = file_get_contents($cssPath);

$settingsRequired = [
    'function siteImagePicker(TenantContext $tenant, string $fieldName, string $selectedUuid',
    'function safeSiteImageMediaUuid(TenantContext $tenant, string $value): string',
    '$topbarPicker = $this->siteImagePicker($tenant, \'topbar_media_uuid\', $topbarMediaUuid, true);',
    '$menuPicker = $this->siteImagePicker($tenant, \'menu_media_uuid\', $menuMediaUuid, true);',
    '$artworkCardPicker = $this->siteImagePicker($tenant, \'artwork_card_media_uuid\', $artworkCardMediaUuid, true);',
    '$backgroundPicker = $this->siteImagePicker($tenant, \'background_media_uuid\', $backgroundMediaUuid, true);',
    '{$topbarPicker}',
    '{$menuPicker}',
    '{$artworkCardPicker}',
    '{$backgroundPicker}',
    "atype.code = 'site_images'",
    'a.status',
];

foreach ($settingsRequired as $needle) {
    if (!str_contains($settings, $needle)) {
        fwrite(STDERR, "Missing SettingsController site-image picker marker: {$needle}\n");
        exit(1);
    }
}

$contentRequired = [
    'function siteImagePicker(TenantContext $tenant, string $fieldName, string $selectedUuid',
    'function safeSiteImageMediaUuid(TenantContext $tenant, string $value): string',
    '$aboutImagePicker = $this->siteImagePicker($tenant, \'about_media_uuid\', $aboutMediaUuid);',
    '$contactImagePicker = $this->siteImagePicker($tenant, \'contact_media_uuid\', $contactMediaUuid);',
    '{$aboutImagePicker}',
    '{$contactImagePicker}',
    "atype.code = 'site_images'",
    'a.status',
];

foreach ($contentRequired as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "Missing ContentController site-image picker marker: {$needle}\n");
        exit(1);
    }
}

$forbiddenSelects = [
    'name="background_media_uuid"',
    'name="topbar_media_uuid"',
    'name="menu_media_uuid"',
    'name="artwork_card_media_uuid"',
    'name="about_media_uuid"',
    'name="contact_media_uuid"',
];

foreach ($forbiddenSelects as $needle) {
    if (preg_match('/<select\b[^>]*' . preg_quote($needle, '/') . '/i', $settings . "\n" . $content)) {
        fwrite(STDERR, "Blind select remains for site image field: {$needle}\n");
        exit(1);
    }
}

$cssRequired = [
    '.site-image-picker',
    '.site-image-picker-card',
    '.site-image-picker-card img',
];

foreach ($cssRequired as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "Missing site-image picker CSS marker: {$needle}\n");
        exit(1);
    }
}

echo "Tenant admin site-image thumbnail picker static checks passed.\n";

// End of file.
