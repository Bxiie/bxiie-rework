<?php

declare(strict_types=1);

/**
 * Static regression guard for ArtsFolio logo rendering.
 *
 * The illustrated ArtsFolio logo must be constrained by max dimensions while
 * preserving its intrinsic aspect ratio. This catches accidental fixed
 * width+height rules that turn the logo into a stretched banner.
 */

$root = dirname(__DIR__, 2);
$problems = [];

$cssExpectations = [
    'public/assets/platform.css' => ['.logo-brand img', '.compact-logo img', 'object-fit: contain !important', 'width: auto !important', 'height: auto !important', 'max-height: 82px !important'],
    'public/assets/tenant-admin.css' => ['.tenant-admin-public-header img', 'object-fit: contain !important', 'width: auto !important', 'height: auto !important'],
    'public/assets/admin-shell-refactor.css' => ['.platform-admin-logo img', 'object-fit: contain !important', 'width: auto !important', 'height: auto !important'],
    'public/assets/auth.css' => ['.auth-logo', 'object-fit: contain !important', 'width: auto !important', 'height: auto !important'],
    'public/assets/error.css' => ['.error-logo', 'object-fit: contain !important', 'width: auto !important', 'height: auto !important'],
    'public/assets/site.css' => ['.site-header .brand img', 'object-fit: contain !important', 'width: auto !important', 'height: auto !important'],
];

foreach ($cssExpectations as $relative => $markers) {
    $path = $root . '/' . $relative;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    if ($contents === '') {
        $problems[] = "Missing or empty CSS file: {$relative}";
        continue;
    }
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $problems[] = "Missing logo aspect marker in {$relative}: {$marker}";
        }
    }
}

$cacheBustedFiles = [
    'app/Http/View/AuthPage.php',
    'app/Http/View/ErrorPage.php',
    'app/Http/View/AdminLayout.php',
    'app/Http/View/TenantAdminLayout.php',
    'app/Http/Controllers/Auth/PasswordAuthController.php',
    'app/Http/Controllers/Platform/HomeController.php',
    'app/Http/Controllers/Platform/DirectoryController.php',
    'app/Http/Controllers/Platform/MarketingController.php',
    'app/Http/Controllers/Platform/PricingController.php',
    'app/Http/Controllers/Platform/HelpController.php',
    'app/Http/Controllers/Platform/SignupController.php',
];

foreach ($cacheBustedFiles as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        continue;
    }
    $contents = (string) file_get_contents($path);
    if (preg_match('/\/assets\/(platform|tenant-admin|auth|error)\.css(?!\?v=20260708-logo-aspect)/', $contents, $matches)) {
        $problems[] = "Uncache-busted logo-sensitive stylesheet include in {$relative}: {$matches[0]}";
    }
}

if ($problems !== []) {
    fwrite(STDERR, "[FAIL] Logo aspect-ratio static check failed:\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}

echo "[PASS] Logo aspect-ratio static check passed.\n";

// End of file.
