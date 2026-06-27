<?php

declare(strict_types=1);

/**
 * Static coverage for billing email notifications.
 */

$root = dirname(__DIR__, 2);

$files = [
    'service' => $root . '/app/Platform/Billing/BillingNotificationService.php',
    'webhook' => $root . '/app/Http/Controllers/Platform/StripeWebhookController.php',
    'billing' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'applicator' => $root . '/scripts/billing/apply_pending_subscription_changes.php',
    'template_checkout' => $root . '/template/email/billing/checkout-completed.txt',
    'template_failed' => $root . '/template/email/billing/payment-failed.txt',
    'template_recovered' => $root . '/template/email/billing/payment-recovered.txt',
    'template_cancelled' => $root . '/template/email/billing/subscription-canceled.txt',
    'template_scheduled' => $root . '/template/email/billing/plan-change-scheduled.txt',
    'template_applied' => $root . '/template/email/billing/plan-change-applied.txt',
    'docs' => $root . '/docs/admin/subscription_billing_notifications.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$service = file_get_contents($files['service']);
$webhook = file_get_contents($files['webhook']);
$billing = file_get_contents($files['billing']);
$applicator = file_get_contents($files['applicator']);
$docs = file_get_contents($files['docs']);
$state = file_get_contents($files['state']);

$required = [
    [$service, 'final class BillingNotificationService', 'billing notification service must exist'],
    [$service, 'EmailOutboxRepository', 'billing notification service must use existing outbox'],
    [$service, 'template/email/billing', 'billing notification service must use billing templates'],
    [$service, 'tenantBillingRecipients', 'billing notification service must resolve tenant recipients'],
    [$service, 'billing.payment_failed', 'billing notification service must queue payment failed email'],
    [$service, 'billing.payment_recovered', 'billing notification service must queue payment recovered email'],
    [$webhook, 'queueCheckoutCompletedFromSession', 'webhook must queue checkout-completed email'],
    [$webhook, 'queuePaymentFailedFromInvoice', 'webhook must queue payment-failed email'],
    [$webhook, 'queuePaymentRecoveredFromInvoice', 'webhook must queue payment-recovered email'],
    [$webhook, 'queueSubscriptionCanceledFromSubscription', 'webhook must queue subscription-canceled email'],
    [$billing, 'queuePlanChangeScheduled', 'tenant billing controller must queue scheduled-change email'],
    [$billing, 'queuePlanUpgraded', 'tenant billing controller must queue immediate-upgrade email'],
    [$applicator, 'queuePlanChangeApplied', 'scheduled applicator must queue applied-change email'],
    [$docs, 'billing.payment_failed', 'admin docs must list billing email template keys'],
    [$state, 'billing email notifications', 'PROJECT_STATE must record billing email notifications'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Subscription billing notification static checks passed.\n";

// End of file.
