<?php

declare(strict_types=1);

/**
 * Static coverage for Billing Health render-injected Actual paying tenants card.
 */

$root = dirname(__DIR__, 2);
$billingPath = $root . '/app/Http/Controllers/Platform/Admin/BillingHealthController.php';
$tenantsPath = $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php';

foreach ([$billingPath, $tenantsPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
}

$billing = file_get_contents($billingPath);
$tenants = file_get_contents($tenantsPath);

$required = [
    [$billing, 'function actualPayingTenantsCard', 'billing health must have actual paying card helper'],
    [$billing, '$this->actualPayingTenantsCard() .', 'billing health must prepend actual paying card to render body'],
    [$billing, 'function actualPayingTenants', 'billing health must have actual paying count helper'],
    [$billing, 'stripe_subscription_id', 'actual paying count must require Stripe subscription confirmation'],
    [$billing, 'monthly_price_cents > 0', 'actual paying count must require paid plan'],
    [$billing, 'COALESCE(t.complementary, 0) = 0', 'actual paying count must exclude complementary tenants when available'],
    [$billing, 'Actual paying tenants', 'billing health must render metric label'],
    [$tenants, 'function tenantSearchPanel', 'tenants controller must have tenant search helper from prior patch'],
    [$tenants, '$this->tenantSearchPanel() .', 'tenants controller must prepend search panel to render body'],
    [$tenants, 'Search results across all tenants', 'tenant search must search all tenants'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    '<?= number_format($this->actualPayingTenants()) ?>',
    '<p class="admin-stat-value"></p>',
];

foreach ($forbidden as $needle) {
    if (str_contains($billing, $needle)) {
        fwrite(STDERR, "Forbidden unevaluated/blank billing health metric: {$needle}\n");
        exit(1);
    }
}

echo "Billing Health actual paying tenants render-injection checks passed.\n";

// End of file.
