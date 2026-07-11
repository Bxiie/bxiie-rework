#!/usr/bin/php
<?php

/**
 * Regression checks for signup-code invite status across all template variants.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$repositoryPath = $root . '/app/Platform/Signup/SignupCodeRepository.php';

if (!is_file($repositoryPath)) {
    fwrite(STDERR, "[FAIL] Missing SignupCodeRepository.php\n");
    exit(1);
}

$repository = (string) file_get_contents($repositoryPath);

$tokens = [
    "'platform.tenant_signup_invite'",
    "'platform.tenant_signup_invite.no_free_period'",
    "'platform.tenant_signup_invite.one_month'",
    "'platform.tenant_signup_invite.multiple_months'",
];

foreach ($tokens as $token) {
    if (substr_count($repository, $token) < 5) {
        fwrite(
            STDERR,
            "[FAIL] Invite-status query does not consistently include {$token}\n"
        );
        exit(1);
    }
}

if (str_contains($repository, "WHERE eo.template_key = 'platform.tenant_signup_invite'")) {
    fwrite(
        STDERR,
        "[FAIL] Legacy single-key invite-status filter is still present.\n"
    );
    exit(1);
}

$requiredMetrics = [
    'invite_email_count',
    'invite_email_sent_count',
    'invite_email_pending_count',
    'invite_email_last_sent_at',
    'invite_email_last_queued_at',
];

foreach ($requiredMetrics as $metric) {
    if (!str_contains($repository, $metric)) {
        fwrite(STDERR, "[FAIL] Missing invite-status metric: {$metric}\n");
        exit(1);
    }
}

echo "[PASS] Signup-code invite status counts every template variant.\n";

// End of file.
