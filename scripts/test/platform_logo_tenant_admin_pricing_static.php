<?php

declare(strict_types=1);

/**
 * Guards platform logo proportions, tenant Admin navigation, curation wiring,
 * and pricing disclosure.
 */

$root = dirname(__DIR__, 2);

$files = [
    'home' => $root . '/app/Http/Controllers/Tenant/HomeController.php',
    'kernel' => $root . '/app/Http/AppKernel.php',
    'pricing' => $root . '/app/Http/Controllers/Platform/PricingController.php',
    'layout' => $root . '/app/Http/View/AdminLayout.php',
    'css' => $root . '/public/assets/tenant-admin.css',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label} file: {$path}\n");
        exit(1);
    }
}

$home = file_get_contents($files['home']);
$kernel = file_get_contents($files['kernel']);
$pricing = file_get_contents($files['pricing']);
$layout = file_get_contents($files['layout']);
$css = file_get_contents($files['css']);

$required = [
    [$home, '$currentUser = $this->currentUser;', 'tenant public navigation must use the request-resolved current user'],
    [$home, "'tenant_owner', 'tenant_admin'", 'tenant Admin link must recognize canonical tenant role slugs'],
    [$home, 'href="/admin">Admin</a>', 'tenant public navigation must render the Admin link'],
    [$home, 'FROM role_assignments ra', 'public Admin link authorization must use tenant-scoped role assignments'],
    [$pricing, 'Curation workflow included', 'pricing cards must mention included workflow'],
    [$pricing, 'Curation workflow not included', 'pricing cards must mention excluded workflow'],
    [$pricing, 'curation_workflow_included', 'pricing must use the stored workflow capability'],
    [$css, 'Platform logo intrinsic-ratio repair', 'platform logo ratio repair CSS must exist'],
    [$css, 'height: auto !important;', 'platform logo height must preserve intrinsic ratio'],
    [$css, 'object-fit: contain;', 'platform logo must use contain fitting'],
    [$layout, 'tenant-admin.css&logo=20260623', 'admin stylesheet cache must be busted'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

$curationPosition = strpos($kernel, '$curationController = new');
$tenantPosition = strpos($kernel, '$tenantController = new TenantHomeController');

if ($curationPosition === false || $tenantPosition === false || $curationPosition > $tenantPosition) {
    fwrite(STDERR, "CurationController must be constructed before TenantHomeController.\n");
    exit(1);
}

echo "Platform logo, tenant Admin link, and pricing workflow static checks passed.\n";

// End of file.
