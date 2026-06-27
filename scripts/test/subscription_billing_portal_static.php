<?php

declare(strict_types=1);

/**
 * Static coverage for Stripe Billing Portal and scheduled-billing automation.
 */

$root = dirname(__DIR__, 2);

$files = [
    'migration' => $root . '/database/migrations/0052_billing_portal_and_scheduler.sql',
    'service' => $root . '/app/Platform/Billing/StripeBillingPortalService.php',
    'billing' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'routes' => $root . '/app/Http/Routes/tenant.php',
    'systemd_service' => $root . '/scripts/systemd/artsfolio-billing-scheduler.service',
    'systemd_timer' => $root . '/scripts/systemd/artsfolio-billing-scheduler.timer',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$migration = file_get_contents($files['migration']);
$service = file_get_contents($files['service']);
$billing = file_get_contents($files['billing']);
$routes = file_get_contents($files['routes']);
$systemdService = file_get_contents($files['systemd_service']);
$systemdTimer = file_get_contents($files['systemd_timer']);
$state = file_get_contents($files['state']);

$required = [
    [$migration, 'billing_portal_last_session_id', 'migration must store portal session id'],
    [$migration, 'payment_method_update_requested_at', 'migration must store payment-method update request timestamp'],
    [$migration, 'latest_stripe_error', 'migration must store latest Stripe portal error'],
    [$service, 'billing_portal/sessions', 'portal service must call Stripe Billing Portal sessions endpoint'],
    [$service, 'customer', 'portal service must pass customer id'],
    [$service, 'return_url', 'portal service must pass return URL'],
    [$billing, 'function managePayment', 'billing controller must expose portal action'],
    [$billing, 'StripeBillingPortalService', 'billing controller must use portal service'],
    [$billing, 'action="/admin/billing/portal"', 'billing page must show update-payment-method form'],
    [$billing, 'stripe_billing_portal_configuration_id', 'billing controller must honor optional portal configuration id'],
    [$routes, "/admin/billing/portal", 'tenant routes must register portal endpoint'],
    [$systemdService, 'apply_pending_subscription_changes.php', 'systemd service must run scheduled billing applicator'],
    [$systemdTimer, 'OnCalendar=hourly', 'systemd timer must run hourly'],
    [$state, 'Stripe Billing Portal', 'PROJECT_STATE must record portal pass'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Subscription billing portal and scheduler static checks passed.\n";

// End of file.
