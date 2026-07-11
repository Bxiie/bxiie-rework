#!/usr/bin/php
<?php

/**
 * Regression checks for complimentary months in prospective signup invites.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'repository' => $root . '/app/Platform/Signup/SignupCodeRepository.php',
    'service' => $root . '/app/Platform/Signup/TenantSignupService.php',
    'controller' => $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php',
    'template' => $root . '/template/email/platform/tenant-signup-invite.txt',
    'migration' => $root . '/database/migrations/0065_signup_code_default_free_month.sql',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing {$label}: {$path}\n");
        exit(1);
    }
    $files[$label] = (string) file_get_contents($path);
}

$checks = [
    'repository defaults free months to one' => str_contains($files['repository'], 'int $freeAccessMonths = 1'),
    'one-time codes retain free months' => str_contains($files['repository'], "['one_time', 'free_months']"),
    'plan grant uses stored month count' => str_contains($files['service'], '$isFreeAccess = $months > 0;'),
    'admin fields default to one month' => substr_count($files['controller'], 'name="free_access_months" min="1" max="60" value="1"') >= 2,
    'bulk one-time creation posts free months' => str_contains($files['controller'], "(int) (\$_POST['free_access_months'] ?? 1)"),
    'invite receives the complete signup-code row' => str_contains($files['controller'], '$this->queueInvite($email, $code);'),
    'invite subject states the free period' => str_contains($files['controller'], "'Your ArtsFolio plan is free for '"),
    'invite template states selected-plan offer' => str_contains($files['template'], 'The plan you select will be free for {{FREE_ACCESS_MONTHS}}.'),
    'database default is one month' => str_contains($files['migration'], 'DEFAULT 1'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

echo "[PASS] Signup invite complimentary-month regression check passed.\n";

// End of file.
