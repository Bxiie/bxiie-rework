<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'database/migrations/0026_sales_readiness_phase1.sql' => ['is_one_off', 'inventory_quantity'],
    'app/Http/Controllers/Tenant/HomeController.php' => ['publicPriceLine', 'artworkSalesPanel', 'cookieConsentBanner', 'contactArtworkSubject'],
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php' => ['sales_inventory_mode', 'inventory_quantity', 'is_one_off'],
    'app/Http/Controllers/Tenant/Admin/SettingsController.php' => ['sales_notes'],
    'public/assets/site.css' => ['.artwork-sales-panel', '.cookie-consent'],
];

foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$relative}\n");
        exit(1);
    }
    $body = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($body, $needle)) {
            fwrite(STDERR, "Missing {$needle} in {$relative}\n");
            exit(1);
        }
    }
}

echo "Sales phase 1 static checks passed.\n";

# End of file.
