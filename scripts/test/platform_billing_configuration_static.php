<?php

declare(strict_types=1);

/**
 * Static coverage for Platform Admin billing configuration validation.
 */

$root = dirname(__DIR__, 2);

$files = [
    'controller' => $root . '/app/Http/Controllers/Platform/Admin/BillingConfigurationController.php',
    'routes' => $root . '/app/Http/Routes/platform.php',
    'layout' => $root . '/app/Http/View/AdminLayout.php',
    'admin_docs' => $root . '/docs/admin/billing_configuration.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$controller = file_get_contents($files['controller']);
$routes = file_get_contents($files['routes']);
$layout = file_get_contents($files['layout']);
$adminDocs = file_get_contents($files['admin_docs']);
$state = file_get_contents($files['state']);

$required = [
    [$controller, 'final class BillingConfigurationController', 'controller class must exist'],
    [$controller, 'stripe_publishable_key', 'controller must validate Stripe publishable key'],
    [$controller, 'stripe_secret_key', 'controller must validate Stripe secret key'],
    [$controller, 'stripe_webhook_secret', 'controller must validate Stripe webhook secret'],
    [$controller, 'stripe_billing_portal_configuration_id', 'controller must validate Billing Portal configuration ID'],
    [$controller, 'paidPlansMissingPriceIds', 'controller must check paid plans missing Price IDs'],
    [$controller, 'freePlansWithPriceIds', 'controller must check free plans with Price IDs'],
    [$controller, 'stripe_webhook_events', 'controller must check webhook event log readiness'],
    [$controller, 'information_schema.columns', 'controller must be schema tolerant'],
    [$routes, 'PlatformAdminBillingConfigurationController', 'platform routes must import billing configuration controller'],
    [$routes, "/platform/admin/billing-configuration", 'platform routes must register billing configuration page'],
    [$layout, "'billing_configuration' => ['/platform/admin/billing-configuration', 'Billing Config']", 'admin nav must include Billing Config'],
    [$adminDocs, 'Billing Configuration', 'admin docs must describe billing configuration page'],
    [$state, 'Platform Admin billing configuration validation', 'PROJECT_STATE must record billing configuration validation'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Platform billing configuration static checks passed.\n";

// End of file.
