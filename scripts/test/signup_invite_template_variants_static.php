#!/usr/bin/php
<?php

/**
 * Regression checks for signup-invite template selection by free-month count.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php';
$catalogPath = $root . '/app/Platform/Email/EmailTemplateCatalog.php';
$templates = [
    $root . '/template/email/platform/tenant-signup-invite-no-free-period.txt',
    $root . '/template/email/platform/tenant-signup-invite-one-month.txt',
    $root . '/template/email/platform/tenant-signup-invite.txt',
];

foreach (array_merge([$controllerPath, $catalogPath], $templates) as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing required file: {$path}\n");
        exit(1);
    }
}

$controller = (string) file_get_contents($controllerPath);
$catalog = (string) file_get_contents($catalogPath);

$checks = [
    'zero-month branch' => str_contains($controller, 'if ($months === 0)'),
    'one-month branch' => str_contains($controller, '} elseif ($months === 1) {'),
    'multiple-month fallback' => str_contains($controller, "platform/tenant-signup-invite.txt"),
    'no-free template key' => str_contains($controller, 'platform.tenant_signup_invite.no_free_period'),
    'one-month template key' => str_contains($controller, 'platform.tenant_signup_invite.one_month'),
    'multiple-month template key' => str_contains($controller, 'platform.tenant_signup_invite.multiple_months'),
    'no-free catalog entry' => str_contains($catalog, "platform/tenant-signup-invite-no-free-period.txt"),
    'one-month catalog entry' => str_contains($catalog, "platform/tenant-signup-invite-one-month.txt"),
    'multiple-month catalog entry' => str_contains($catalog, "platform/tenant-signup-invite.txt"),
    'recipient placeholder expanded' => str_contains($controller, "'{{ recipient_email }}' => \$email"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

echo "[PASS] Signup invite template variants static check passed.\n";

// End of file.
