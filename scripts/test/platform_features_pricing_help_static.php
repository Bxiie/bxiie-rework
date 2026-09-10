<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'database/migrations/0073_studio_custom_domain_entitlement.sql' => ["WHERE slug = 'studio'", 'custom_domain_included = FALSE'],
    'app/Http/Controllers/Platform/PricingController.php' => ['Instagram publishing', 'Custom domain', "\$instagram .=", "custom_domain_included", 'data-admin-users-added="1"', "=== 'collective'", "'Unlimited admin users'", "'<tr><td>Admin users</td>'"],
    'app/Http/Controllers/Platform/MarketingController.php' => ['public function features', 'href="/features"', 'Studio, Professional, Collective', 'Professional, Collective', 'Release Group', 'Bulk artwork upload'],
    'app/Http/View/PlatformChrome.php' => ["'features' => ['/features', 'Features']"],
    'app/Http/Routes/platform.php' => ["\$router->get('/features'"],
    'app/Http/Controllers/Platform/HelpController.php' => ["'artwork-publishing'", "'instagram-publishing'", 'Bulk upload and website releases', 'never create Instagram posts', 'deduplicates hashtags', 'Invalid redirect URI'],
    'docs/user/pricing-plan-details.md' => ['Instagram publishing is included with Studio, Professional, and Collective', 'Custom domains are included with Professional and Collective', '`/features`'],
    'docs/user/tenant-admin-help.md' => ['/help/artwork-publishing', '/help/instagram-publishing', 'Bulk upload and website release scheduling', 'Release Groups publish assigned artwork to the website together'],
    'docs/user/instagram-publishing.md' => ['secondary carousel image', 'Duplicate hashtags are removed'],
    'scripts/test/fixtures/route_inventory.json' => ['"path": "/features"'],
];

$failures = [];
foreach ($checks as $file => $needles) {
    $contents = is_file($root . '/' . $file) ? (file_get_contents($root . '/' . $file) ?: '') : '';
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) $failures[] = "{$file} missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Platform features, pricing, and help checks passed.\n";
