<?php

declare(strict_types=1);

/**
 * Regression checks for signup-code free-access duration.
 */

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Platform/Signup/TenantSignupService.php'
);
$tenants = (string) file_get_contents(
    $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php'
);

$failures = [];

$serviceMarkers = [
    '$currentPeriodEndsAt = $isFreeAccess',
    '? $complimentaryUntil',
    "'current_period_ends_at' => \$currentPeriodEndsAt",
    "'complimentary_until' => \$complimentaryUntil",
];

foreach ($serviceMarkers as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "TenantSignupService missing marker: {$marker}";
    }
}

$tenantsMarkers = [
    "\$complimentaryUntilRaw = trim((string) (\$row['complimentary_until'] ?? ''))",
    "\$trialEndRaw = \$rawStatus === 'trial'",
    '? $complimentaryUntilRaw',
    ': $periodEndRaw',
    '$this->trialPeriodDetails($trialEndRaw)',
];

foreach ($tenantsMarkers as $marker) {
    if (!str_contains($tenants, $marker)) {
        $failures[] = "TenantsController missing marker: {$marker}";
    }
}

$stale = "'current_period_ends_at' => (new \\DateTimeImmutable('now'))->modify('+1 month')";
if (str_contains($service, $stale)) {
    $failures[] = 'Signup trial still hard-codes current_period_ends_at to one month.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Signup-code trial duration check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Signup-code trial duration follows free_access_months.\n";

// End of file.
