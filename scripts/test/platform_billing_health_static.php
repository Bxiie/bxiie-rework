<?php

declare(strict_types=1);

/**
 * Static coverage for Platform Admin billing health diagnostics.
 */

$root = dirname(__DIR__, 2);

$files = [
    'controller' => $root . '/app/Http/Controllers/Platform/Admin/BillingHealthController.php',
    'routes' => $root . '/app/Http/Routes/platform.php',
    'layout' => $root . '/app/Http/View/AdminLayout.php',
    'admin_docs' => $root . '/docs/admin/billing_health.md',
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
    [$controller, 'final class BillingHealthController', 'controller class must exist'],
    [$controller, 'Paid plans missing Stripe Price IDs', 'controller must check paid plan price IDs'],
    [$controller, 'Past-due subscriptions', 'controller must check past-due tenants'],
    [$controller, 'Overdue scheduled downgrades/cancellations', 'controller must check overdue scheduled changes'],
    [$controller, 'Failed Stripe webhook events', 'controller must check failed webhooks'],
    [$controller, 'stripe_webhook_events', 'controller must inspect webhook table when present'],
    [$controller, 'information_schema.columns', 'controller must be schema tolerant'],
    [$controller, 'schema-tolerant ORDER BY', 'controller must not require tenant_plan_assignments.updated_at'],
    [$controller, 'latest_stripe_error', 'controller must surface portal/Stripe errors'],
    [$routes, 'PlatformAdminBillingHealthController', 'platform routes must import billing health controller'],
    [$routes, "/platform/admin/billing-health", 'platform routes must register billing health page'],
    [$layout, "'billing_health' => ['/platform/admin/billing-health', 'Billing Health']", 'admin nav must include Billing Health'],
    [$adminDocs, 'Billing Health', 'admin docs must describe billing health page'],
    [$state, 'Platform Admin billing health dashboard', 'PROJECT_STATE must record billing health dashboard'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Platform billing health static checks passed.\n";

// End of file.
