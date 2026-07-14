<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php'
);
$failures = [];

foreach ([
    '$this->billingDetailsPanel($tenant, $billing)',
    'private function billingDetailsPanel(TenantContext $tenant, array $billing): string',
    '$this->complimentaryBillingSummary($tenant, $billing)',
    '<strong>Complimentary access:</strong>',
    '<strong>Platform billing begins:</strong>',
    'private function complimentaryBillingSummary(TenantContext $tenant, array $billing): array',
    "'status' => 'Active with no scheduled expiration'",
    "'billing_begins' => 'Not scheduled while complimentary status remains active'",
    '\'status\' => \'Active through \' . $date',
    '\'billing_begins\' => $date',
    '\'status\' => \'Expired on \' . $date',
    'current_period_ends_at = VALUES(current_period_ends_at)',
    'billing_status = "trial"',
] as $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "BillingController missing marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant billing complimentary-status check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Tenant billing shows complimentary status and billing start date.\n";

// End of file.
