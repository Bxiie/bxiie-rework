<?php

declare(strict_types=1);

/**
 * Static coverage for billing delinquency policy and audit tooling.
 */

$root = dirname(__DIR__, 2);

$files = [
    'policy' => $root . '/app/Platform/Billing/BillingDelinquencyPolicy.php',
    'auditor' => $root . '/scripts/billing/audit_billing_delinquency.php',
    'admin_docs' => $root . '/docs/admin/subscription_billing_delinquency.md',
    'dev_docs' => $root . '/docs/dev/subscription_billing.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$policy = file_get_contents($files['policy']);
$auditor = file_get_contents($files['auditor']);
$adminDocs = file_get_contents($files['admin_docs']);
$devDocs = file_get_contents($files['dev_docs']);
$state = file_get_contents($files['state']);

$required = [
    [$policy, 'final class BillingDelinquencyPolicy', 'policy helper must exist'],
    [$policy, 'public const GRACE_DAYS = 7', 'policy must define 7-day grace threshold'],
    [$policy, 'public const RESTRICTION_DAYS = 14', 'policy must define 14-day restriction threshold'],
    [$policy, 'public const FINAL_REVIEW_DAYS = 30', 'policy must define 30-day final review threshold'],
    [$policy, "public const STATE_GRACE = 'grace'", 'policy must define grace state'],
    [$policy, "public const STATE_RESTRICT = 'restrict'", 'policy must define restrict state'],
    [$policy, "public const STATE_FINAL_REVIEW = 'final_review'", 'policy must define final review state'],
    [$policy, 'stateFor', 'policy must classify delinquency state'],
    [$policy, 'actionForState', 'policy must describe operator action'],
    [$auditor, 'Read-only billing delinquency audit', 'auditor file must identify itself'],
    [$auditor, 'bootstrap/app.php', 'auditor must use existing application bootstrap'],
    [$auditor, 'config/database.php', 'auditor must use existing database config'],
    [$auditor, "tpa.billing_status IN (\\'past_due\\', \\'unpaid\\')", 'auditor must inspect past_due and unpaid tenants'],
    [$auditor, 'BillingDelinquencyPolicy::stateFor', 'auditor must use delinquency policy helper'],
    [$auditor, 'billing_action_required_at', 'auditor must inspect action-required timestamp'],
    [$adminDocs, 'Billing Delinquency Audit', 'admin docs must describe delinquency audit'],
    [$devDocs, 'Billing delinquency policy', 'dev docs must describe delinquency policy'],
    [$state, 'billing delinquency policy', 'PROJECT_STATE must record delinquency policy'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Subscription billing delinquency static checks passed.\n";

// End of file.
