#!/usr/bin/php
<?php

/**
 * Static regression checks for pricing/billing/auth/social stabilization.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'public/index.php' => ["/password/forgot", "->updatePlan(\$request, \$tenant, \$currentUser)", "Location' => '/' . \$contactSlug"],
    'app/Http/Controllers/Platform/Admin/PricingController.php' => ['allow_sales', 'allowed_storage_gb', 'allowed_contact_messages', 'allowed_admin_users'],
    'app/Http/Controllers/Tenant/Admin/BillingController.php' => ['Feature usage by selected pricing tier', 'updatePlan', 'Complementary plan'],
    'app/Http/Controllers/Tenant/HomeController.php' => ['socialFooterLinks', 'effectivePlanSlug', 'instagram_url'],
    'app/Http/Controllers/Tenant/SalesController.php' => ['saveCartContact', 'allow_sales', 'customer_email'],
    'scripts/email/queue_abandoned_cart_emails.php' => ['abandoned_12h_email_sent_at', 'abandoned_24h_email_sent_at', 'EmailOutboxRepository'],
    'database/migrations/0028_pricing_billing_auth_social_stabilization.sql' => ['allow_sales', 'complementary', 'customer_email'],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }
    $text = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            fwrite(STDERR, "{$file} missing {$needle}\n");
            exit(1);
        }
    }
}

echo "Pricing, billing, auth, social, and cart reminder wiring is present.\n";

// End of file.
