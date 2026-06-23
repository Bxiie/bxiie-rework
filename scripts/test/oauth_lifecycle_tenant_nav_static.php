<?php

declare(strict_types=1);

/**
 * Regression checks for provider-neutral lifecycle scheduling and tenant-nav
 * authentication/cache isolation.
 */

$root = dirname(__DIR__, 2);
$signup = file_get_contents(
    $root . '/app/Platform/Signup/TenantSignupService.php'
);
$signupController = file_get_contents(
    $root . '/app/Http/Controllers/Platform/SignupController.php'
);
$home = file_get_contents(
    $root . '/app/Http/Controllers/Tenant/HomeController.php'
);
$reconcile = file_get_contents(
    $root . '/scripts/email/reconcile_tenant_lifecycle_emails.php'
);

if (
    $signup === false
    || $signupController === false
    || $home === false
    || $reconcile === false
) {
    fwrite(STDERR, "[FAIL] Could not read lifecycle/nav source files.\n");
    exit(1);
}

$signupNeedles = [
    '$this->queueLifecycleEmail(',
    '$this->lifecycleEmailExists(',
    'tenant_admin_welcome_6h',
    'tenant_admin_feature_deep_dive_1d',
];

$controllerNeedles = [
    'existingUserId: $oauthUserId',
    'createPasswordIdentity: $oauthProfile === null',
];

$homeNeedles = [
    'private function tenantAdminLink(TenantContext $tenant)',
    "tm.status = 'active'",
    "r.slug IN ('owner', 'admin')",
    "'Cache-Control' => 'private, no-store, max-age=0'",
    "'Vary' => 'Cookie'",
];

$reconcileNeedles = [
    '--tenant-slug=facebooktest',
    '[MISSING]',
    'tenant_admin_welcome_6h',
    'tenant_admin_feature_deep_dive_1d',
];

foreach ($signupNeedles as $needle) {
    if (!str_contains($signup, $needle)) {
        fwrite(STDERR, "[FAIL] TenantSignupService.php missing: {$needle}\n");
        exit(1);
    }
}

foreach ($controllerNeedles as $needle) {
    if (!str_contains($signupController, $needle)) {
        fwrite(STDERR, "[FAIL] SignupController.php missing: {$needle}\n");
        exit(1);
    }
}

foreach ($homeNeedles as $needle) {
    if (!str_contains($home, $needle)) {
        fwrite(STDERR, "[FAIL] HomeController.php missing: {$needle}\n");
        exit(1);
    }
}

foreach ($reconcileNeedles as $needle) {
    if (!str_contains($reconcile, $needle)) {
        fwrite(STDERR, "[FAIL] Reconciliation script missing: {$needle}\n");
        exit(1);
    }
}

echo "[PASS] OAuth lifecycle and tenant-nav isolation checks passed.\n";

// End of file.
