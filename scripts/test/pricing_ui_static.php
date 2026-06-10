<?php

/**
 * Static checks for pricing-page UI content and contrast.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$pricingContent = '';
foreach ([
    $root . '/app/Http/Controllers/Platform/MarketingController.php',
    $root . '/app/Http/Controllers/Platform/PricingController.php',
    $root . '/app/Http/Controllers/Platform/Admin/PricingController.php',
] as $file) {
    if (is_file($file)) {
        $pricingContent .= "\n" . (string) file_get_contents($file);
    }
}

if (!str_contains($pricingContent, 'admin_user_limit') && !str_contains($pricingContent, 'Admin users')) {
    fwrite(STDERR, "FAILED: pricing page does not show admin user count per tier.\n");
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
        $cssContent .= "\n" . (string) file_get_contents($file);
    }
}

if (!str_contains($cssContent, 'Pricing contrast override: keep plan prices visible')) {
    fwrite(STDERR, "FAILED: pricing CSS contrast override is missing.\n");
    exit(1);
}

if (!str_contains($cssContent, 'background: rgba(255, 255, 255') || !str_contains($cssContent, 'color: #1f1a14')) {
    fwrite(STDERR, "FAILED: pricing CSS does not force readable price contrast.\n");
    exit(1);
}

echo "Pricing UI static checks passed.\n";

// End of file.
