#!/usr/bin/php
<?php

/**
 * Regression checks for canonical tenant-admin branding and navigation.
 */

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

$root = dirname(__DIR__, 2);
$legacyPath = $root . '/app/Http/Controllers/Tenant/Admin/AdminLayout.php';
$canonicalPath = $root . '/app/Http/View/TenantAdminLayout.php';
$navPath = $root . '/app/Http/View/TenantAdminNav.php';
$contentPath = $root . '/app/Http/Controllers/Tenant/Admin/ContentController.php';

foreach ([$legacyPath, $canonicalPath, $navPath, $contentPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing required file: {$path}\n");
        exit(1);
    }
}

$legacy = (string) file_get_contents($legacyPath);
$canonical = (string) file_get_contents($canonicalPath);
$nav = (string) file_get_contents($navPath);
$content = (string) file_get_contents($contentPath);

$checks = [
    'legacy layout delegates to canonical layout' =>
        str_contains($legacy, '\App\Http\View\AdminLayout::render('),
    'legacy layout contains no Bxiie branding' =>
        !str_contains(strtolower($legacy), 'bxiie'),
    'canonical layout reads tenant site title' =>
        str_contains($canonical, "'site_title'"),
    'canonical layout renders tenant-aware sidebar title' =>
        str_contains($canonical, '{$siteTitle}'),
    'content page still uses the compatibility layout' =>
        str_contains($content, 'AdminLayout::render'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

$requiredNavigation = [
    "'dashboard' => ['/admin', 'Dashboard']",
    "'settings' => ['/admin/settings', 'Settings']",
    "'content' => ['/admin/content', 'Content']",
    "'artworks' => ['/admin/artworks', 'Artworks']",
    "'curation' => ['/admin/curation', 'Curation']",
    "'sections' => ['/admin/portfolio-sections', 'Portfolio Sections']",
    "'events' => ['/admin/events', 'Events']",
    "'messages' => ['/admin/contact-messages', 'Messages']",
    "'email' => ['/admin/email-signups', 'Email Signups']",
    "'domains' => ['/admin/domains', 'Domains']",
    "'billing' => ['/admin/billing', 'Billing']",
    "'sales' => ['/admin/sales', 'Sales']",
    "'sales_analytics' => ['/admin/sales/analytics', 'Sales Analytics']",
    "'users' => ['/admin/users', 'Users']",
    "'stats' => ['/admin/stats', 'Stats']",
    "'audit' => ['/admin/audit-log', 'Audit Log']",
    "'routes' => ['/admin/routes', 'Tenant Routes']",
];

foreach ($requiredNavigation as $marker) {
    if (!str_contains($nav, $marker)) {
        fwrite(STDERR, "[FAIL] Missing canonical tenant-admin navigation item: {$marker}\n");
        exit(1);
    }
}

echo "[PASS] Tenant-admin pages use tenant branding and complete canonical navigation.\n";

// End of file.
