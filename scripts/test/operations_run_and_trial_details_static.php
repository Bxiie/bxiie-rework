<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$controller = (string) file_get_contents($root . '/app/Http/Controllers/Platform/Admin/OperationsController.php');
$repository = (string) file_get_contents($root . '/app/Platform/Monitoring/OperationsMonitorRepository.php');
$tenants = (string) file_get_contents($root . '/app/Http/Controllers/Platform/Admin/TenantsController.php');

foreach (['ArtsFolio operations run detail failed for run', "is_array(\$run['metrics'] ?? null)", 'This monitor run has no saved metric rows.', 'private function normalizedRunStatus'] as $marker) {
    if (!str_contains($controller, $marker)) { $failures[] = "OperationsController missing marker: {$marker}"; }
}
if (!str_contains($repository, 'ORDER BY CASE metric_status')) { $failures[] = 'Operations repository does not use portable CASE ordering.'; }
foreach (["\$trialDetails = \$rawStatus === 'trial'", 'private function trialPeriodDetails', 'billing starts ', 'private function relativeBillingTime', "format('M j, Y g:i A T')"] as $marker) {
    if (!str_contains($tenants, $marker)) { $failures[] = "TenantsController missing marker: {$marker}"; }
}
if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Operations run and trial details check failed:\n");
    foreach ($failures as $failure) { fwrite(STDERR, "[FAIL]  - {$failure}\n"); }
    exit(1);
}
echo "[PASS] Operations run and trial details check passed.\n";

// End of file.
