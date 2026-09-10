<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = file_get_contents($root . '/database/migrations/0074_complementary_plan_ceiling.sql') ?: '';
$platform = file_get_contents($root . '/app/Http/Controllers/Platform/Admin/TenantsController.php') ?: '';
$billing = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/BillingController.php') ?: '';

$checks = [
    [$migration, 'ADD COLUMN IF NOT EXISTS complementary_max_plan_id', 'migration adds the per-tenant plan ceiling'],
    [$migration, 'ORDER BY p.display_order DESC', 'existing complementary tenants retain the highest active plan'],
    [$platform, 'name="complementary_max_plan_id"', 'platform tenant form exposes the ceiling'],
    [$platform, 'activePlanExists($maxPlanId)', 'platform save validates the selected active plan'],
    [$platform, "'complementary_max_plan_id' => \$enabled === 1 ? \$maxPlanId : null", 'audit records the ceiling'],
    [$billing, 'complementaryPlanAllows($tenant, $targetPlan)', 'tenant plan change checks the complementary ceiling'],
    [$billing, '$this->assignPlan($tenant, (int) $targetPlan[\'id\']);', 'eligible changes apply locally'],
    [$billing, 'No billing checkout was required', 'eligible changes confirm billing was bypassed'],
];

$failures = [];
foreach ($checks as [$haystack, $needle, $label]) {
    if (!str_contains($haystack, $needle)) $failures[] = $label;
}

$allowancePosition = strpos($billing, 'if ($this->complementaryPlanAllows($tenant, $targetPlan))');
$checkoutPosition = strpos($billing, '$this->recordPendingPaidPlanChange(');
if ($allowancePosition === false || $checkoutPosition === false || $allowancePosition >= $checkoutPosition) {
    $failures[] = 'complementary allowance must be handled before Stripe checkout is prepared';
}

if ($failures !== []) {
    fwrite(STDERR, "Complementary plan ceiling checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Complementary plan ceiling static checks passed.\n";

// End of file.
