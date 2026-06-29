<?php

declare(strict_types=1);

/**
 * Static coverage for About/Contact selected site images.
 *
 * Keep this test simple: HomeController::contact() contains a large heredoc,
 * so this test intentionally avoids brace-walking the method body.
 */

$root = dirname(__DIR__, 2);
$contentPath = $root . '/app/Http/Controllers/Tenant/Admin/ContentController.php';
$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';

foreach ([$contentPath, $homePath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
}

$content = file_get_contents($contentPath);
$home = file_get_contents($homePath);

$contentRequired = [
    "'about_media_uuid'",
    "'contact_media_uuid'",
    'safeSiteImageMediaUuid($tenant, $value)',
    '$aboutImagePicker = $this->siteImagePicker($tenant, \'about_media_uuid\', $aboutMediaUuid);',
    '$contactImagePicker = $this->siteImagePicker($tenant, \'contact_media_uuid\', $contactMediaUuid);',
];

foreach ($contentRequired as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "Missing ContentController About/Contact image marker: {$needle}\n");
        exit(1);
    }
}

$homeRequired = [
    '$aboutImageHtml = $this->siteImageFigure($tenant, \'about_media_uuid\', \'about_image_opacity\', \'About image\');',
    '{$aboutImageHtml}',
    '$this->siteImageFigure($tenant, \'contact_media_uuid\', \'contact_image_opacity\', \'Contact image\')',
    'private function siteImageFigure(TenantContext $tenant, string $mediaSetting, string $opacitySetting, string $alt): string',
    'private function isPublishedSiteImage(TenantContext $tenant, string $uuid): bool',
    "a.status = 'published'",
    "atype.code = 'site_images'",
    'site-content-image',
];

foreach ($homeRequired as $needle) {
    if (!str_contains($home, $needle)) {
        fwrite(STDERR, "Missing HomeController About/Contact site image marker: {$needle}\n");
        exit(1);
    }
}

echo "About/Contact selected site image static checks passed.\n";

// End of file.
