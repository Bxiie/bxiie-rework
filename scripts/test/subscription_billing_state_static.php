<?php

declare(strict_types=1);

/**
 * Static coverage for canonical billing state vocabulary and auditor.
 */

$root = dirname(__DIR__, 2);

$files = [
    'status' => $root . '/app/Platform/Billing/BillingStatus.php',
    'auditor' => $root . '/scripts/billing/audit_billing_state.php',
    'admin_docs' => $root . '/docs/admin/subscription_billing_state.md',
    'dev_docs' => $root . '/docs/dev/subscription_billing.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$status = file_get_contents($files['status']);
$auditor = file_get_contents($files['auditor']);
$adminDocs = file_get_contents($files['admin_docs']);
$devDocs = file_get_contents($files['dev_docs']);
$state = file_get_contents($files['state']);

$required = [
    [$status, 'final class BillingStatus', 'BillingStatus helper must exist'],
    [$status, "public const ACTIVE = 'active'", 'BillingStatus must define active'],
    [$status, "public const PAYMENT_PENDING = 'payment_pending'", 'BillingStatus must define payment_pending'],
    [$status, "public const PAST_DUE = 'past_due'", 'BillingStatus must define past_due'],
    [$status, "public const UNPAID = 'unpaid'", 'BillingStatus must define unpaid'],
    [$status, "public const CANCELED = 'canceled'", 'BillingStatus must define canceled'],
    [$status, "public const CHANGE_DOWNGRADE = 'downgrade'", 'BillingStatus must define downgrade'],
    [$status, "public const CHANGE_CANCEL = 'cancel'", 'BillingStatus must define cancel'],
    [$status, 'requiresPaymentAction', 'BillingStatus must expose payment action helper'],
    [$status, 'isActiveAccess', 'BillingStatus must expose access helper'],
    [$auditor, 'bootstrap/app.php', 'auditor must use the existing application bootstrap'],
    [$auditor, 'config/database.php', 'auditor must use the existing database config'],
    [$auditor, 'unknown_billing_status', 'auditor must catch unknown billing statuses'],
    [$auditor, 'unknown_pending_change_type', 'auditor must catch unknown pending changes'],
    [$auditor, 'pending_change_missing_effective_at', 'auditor must catch incomplete scheduled changes'],
    [$auditor, 'paid_billing_status_missing_subscription', 'auditor must catch paid assignments missing subscription IDs'],
    [$adminDocs, 'Billing State Audit', 'admin docs must describe billing state audit'],
    [$devDocs, 'Billing state vocabulary', 'dev docs must describe canonical billing states'],
    [$state, 'canonical billing state helper', 'PROJECT_STATE must record billing state helper'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Subscription billing state static checks passed.\n";

// End of file.
