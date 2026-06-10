<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$pricingContent = '';
foreach ([
    $root . '/app/Http/Controllers/Platform/MarketingController.php',
    $root . '/app/Http/Controllers/Platform/PricingController.php',
] as $file) {
    if (is_file($file)) {
        $pricingContent .= "
" . (string) file_get_contents($file);
    }
}

if (!str_contains($pricingContent, 'admin_user_limit') && !str_contains($pricingContent, 'Admin users')) {
    fwrite(STDERR, "FAILED: pricing page does not expose admin-user limits.
");
    exit(1);
}

$cssContent = '';
foreach ([
    $root . '/public/assets/platform.css',
    $root . '/public/assets/site.css',
    $root . '/public/assets/marketing.css',
    $root . '/public/assets/admin.css',
] as $file) {
    if (is_file($file)) {
        $cssContent .= "
" . (string) file_get_contents($file);
    }
}

foreach ([
    'Pricing card legibility: dark/featured cards',
    'rgba(255, 255, 255, 0.86)',
    'Plan editor usability',
    'min-width: 18rem',
] as $needle) {
    if (!str_contains($cssContent, $needle)) {
        fwrite(STDERR, "FAILED: pricing/editor CSS missing {$needle}
");
        exit(1);
    }
}

echo "Pricing and plan editor UI static checks passed.
";

// End of file.
