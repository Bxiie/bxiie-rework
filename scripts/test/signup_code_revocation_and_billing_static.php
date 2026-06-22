<?php

declare(strict_types=1);

/**
 * Static regression checks for signup-code revocation and billing redemption.
 */

$root = dirname(__DIR__, 2);
$files = [
    'repository' => $root . '/app/Platform/Signup/SignupCodeRepository.php',
    'signupCodesController' => $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php',
    'billingController' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'platformRoutes' => $root . '/app/Http/Routes/platform.php',
    'tenantRoutes' => $root . '/app/Http/Routes/tenant.php',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$name} file: {$path}
");
        exit(1);
    }
    $files[$name] = file_get_contents($path) ?: '';
}

$checks = [
    'repository exposes revoke method' => str_contains($files['repository'], 'public function revoke(int $codeId): void'),
    'repository validates free code for existing tenant' => str_contains($files['repository'], 'validateFreeAccessForExistingTenant'),
    'repository limits tenant billing redemption to free codes' => str_contains($files['repository'], 'Only free access signup codes can be applied from tenant billing.'),
    'platform admin controller has revoke action' => str_contains($files['signupCodesController'], 'public function revoke(Request $request'),
    'platform admin page posts revoke route' => str_contains($files['signupCodesController'], '/platform/admin/signup-codes/revoke'),
    'platform revoke route is mounted' => str_contains($files['platformRoutes'], "/platform/admin/signup-codes/revoke"),
    'tenant billing controller has free access action' => str_contains($files['billingController'], 'public function applyFreeAccessCode'),
    'tenant billing form posts free access route' => str_contains($files['billingController'], '/admin/billing/free-access-code'),
    'tenant billing redemption writes complimentary_until' => str_contains($files['billingController'], 'complimentary_until'),
    'tenant billing redemption marks code redeemed' => str_contains($files['billingController'], 'markRedeemed((int) $signupCode'),
    'tenant billing route is mounted' => str_contains($files['tenantRoutes'], "/admin/billing/free-access-code"),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Failed signup-code revocation/billing static checks:
- " . implode("
- ", $failed) . "
");
    exit(1);
}

echo "Signup-code revocation and tenant billing redemption wiring is present.
";

// End of file.
