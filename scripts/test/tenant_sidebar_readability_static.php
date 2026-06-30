<?php

declare(strict_types=1);

/**
 * Static regression coverage for readable tenant navigation/sidebar text.
 *
 * Newly created tenants inherit public/assets/site.css into tenant custom CSS.
 * The default menu panel is light/tan, so navigation text must default to a
 * dark value rather than inheriting white from older dark-sidebar rules.
 */

$root = dirname(__DIR__, 2);
$siteCssPath = $root . '/public/assets/site.css';
$adminCssPath = $root . '/public/assets/tenant-admin.css';
$signupServicePath = $root . '/app/Platform/Signup/TenantSignupService.php';

$siteCss = is_file($siteCssPath) ? (string) file_get_contents($siteCssPath) : '';
$adminCss = is_file($adminCssPath) ? (string) file_get_contents($adminCssPath) : '';
$signupService = is_file($signupServicePath) ? (string) file_get_contents($signupServicePath) : '';

$failures = [];

if (!str_contains($siteCss, 'Tenant navigation readability')) {
    $failures[] = 'public/assets/site.css is missing the tenant navigation readability block.';
}

if (!str_contains($siteCss, '--menu-text-color, var(--text-color, #1f1a14)')) {
    $failures[] = 'Tenant public navigation does not default menu text to dark text_color.';
}

if (!preg_match('/\.site-header\s+nav\s+a,\s*\n\.site-header\s+nav\s+\.link-button/s', $siteCss)) {
    $failures[] = 'Tenant public navigation readability block does not cover links and form buttons.';
}

if (!str_contains($adminCss, 'Tenant admin sidebar readability')) {
    $failures[] = 'public/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout is missing the tenant admin sidebar readability block.';
}

if (!str_contains($adminCss, '.tenant-admin-sidebar') || !str_contains($adminCss, 'color: #fff !important')) {
    $failures[] = 'Tenant admin application sidebar white-on-dark rule is not preserved.';
}

if (!str_contains($signupService, "public/assets/site.css")) {
    $failures[] = 'New tenant CSS seeding no longer includes public/assets/site.css.';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "Failed tenant sidebar readability static check: {$failure}\n");
    }
    exit(1);
}

echo "Tenant sidebar readability static checks passed.\n";

// End of file.
