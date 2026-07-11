#!/usr/bin/php
<?php

/**
 * Regression checks for complimentary signup access and Stripe checkout gating.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$servicePath = $root . '/app/Platform/Signup/TenantSignupService.php';
$controllerPath = $root . '/app/Http/Controllers/Platform/SignupController.php';

foreach ([$servicePath, $controllerPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing required source file: {$path}\n");
        exit(1);
    }
}

$service = (string) file_get_contents($servicePath);
$controller = (string) file_get_contents($controllerPath);

$checks = [
    'positive free months establish complimentary access' =>
        str_contains($service, '$isFreeAccess = $months > 0;'),
    'signup result returns complimentary month count' =>
        str_contains($service, "'complimentary_months' => \$complimentaryMonths"),
    'signup result returns complimentary end date' =>
        str_contains($service, "'complimentary_until' => \$complimentaryUntil"),
    'checkout decision requires no complimentary months' =>
        str_contains($service, "'requires_immediate_checkout' =>")
        && str_contains($service, '$complimentaryMonths < 1'),
    'controller uses explicit checkout decision' =>
        str_contains($controller, "(\$result['requires_immediate_checkout'] ?? false) === true"),
    'controller no longer redirects based only on paid price' =>
        !str_contains(
            $controller,
            "if ((int) (\$result['selected_plan_monthly_price_cents'] ?? 0) > 0 && \$this->settings !== null)"
        ),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

echo "[PASS] Complimentary signup checkout regression check passed.\n";

// End of file.
