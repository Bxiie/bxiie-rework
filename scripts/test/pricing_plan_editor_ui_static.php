<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$cssContent = '';
foreach ([
    $root . '/public/assets/platform.css',
    $root . '/public/assets/site.css',
    $root . '/public/assets/marketing.css',
    $root . '/public/assets/admin.css',
] as $file) {
    if (is_file($file)) {
        $cssContent .= "\n" . (string) file_get_contents($file);
    }
}

foreach ([
    'Pricing card legibility: dark/featured cards',
    'rgba(255, 255, 255, 0.88)',
    'Plan editor usability',
    'min-width: 18rem',
] as $needle) {
    if (!str_contains($cssContent, $needle)) {
        fwrite(STDERR, "FAILED: pricing/editor CSS missing {$needle}\n");
        exit(1);
    }
}

$source = '';
foreach ([
    $root . '/app/Http/Controllers/Platform/MarketingController.php',
    $root . '/app/Http/Controllers/Platform/PricingController.php',
    $root . '/app/Http/Controllers/Platform/Admin/PricingController.php',
] as $file) {
    if (is_file($file)) {
        $source .= "\n" . (string) file_get_contents($file);
    }
}
foreach ([$root . '/database/migrations', $root . '/migrations'] as $dir) {
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.sql') ?: [] as $file) {
            $source .= "\n" . (string) file_get_contents($file);
        }
    }
}

if (!str_contains($source, 'admin_user_limit') && !str_contains($source, 'Admin users')) {
    fwrite(STDERR, "FAILED: no admin-user plan limit source found for pricing display.\n");
    exit(1);
}

echo "Pricing and plan editor UI static checks passed.\n";

// End of file.
