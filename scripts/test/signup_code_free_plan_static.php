#!/usr/bin/php
<?php

/**
 * Static regression checks for free-month signup codes and plan assignment.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'database/migrations/0035_signup_code_free_plan_months.sql' => [
        'free_access_months',
        'complimentary_until',
        'granted_by_signup_code_id',
    ],
    'app/Platform/Signup/SignupCodeRepository.php' => [
        "'free_months'",
        'freeAccessMonths',
        "'AFF'",
        'listActivePlans',
    ],
    'app/Platform/Signup/TenantSignupService.php' => [
        'selectedPlanSlug',
        'validateSelectedPlan',
        'assignSignupCodePlanGrant',
        "'status' => 'trial'",
        'complimentary_until',
    ],
    'app/Http/Controllers/Platform/SignupController.php' => [
        'freeAccessPlanBlock',
        'selected_plan',
        'This signup code grants free access',
    ],
    'app/Http/Controllers/Platform/Admin/SignupCodesController.php' => [
        'Free access code',
        'free_access_months',
        'Free access months',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }
    $text = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            fwrite(STDERR, "{$file} missing {$needle}\n");
            exit(1);
        }
    }
}

echo "Free-month signup code wiring is present.\n";

// End of file.
