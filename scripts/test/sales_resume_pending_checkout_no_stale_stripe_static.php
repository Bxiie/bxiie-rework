<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$salesController = $root . '/app/Http/Controllers/Tenant/SalesController.php';
$source = file_get_contents($salesController);
$failures = [];

if ($source === false) {
    fwrite(STDERR, "Could not read SalesController.php\n");
    exit(1);
}

$required = [
    "Stripe lookup failed for pending order" => "Resume flow logs Stripe lookup failures before releasing a local attempt.",
    "checkout_lookup_failed" => "Resume flow marks lookup-failed attempts as released, not pending.",
    "Saved hosted URLs" => "Resume flow documents why stale Stripe URLs are unsafe.",
    "\$liveCheckoutUrl = trim((string) (\$session['url'] ?? \$checkoutUrl));" => "Resume flow uses Stripe's freshly retrieved open Session URL.",
    "in_array(\$paymentStatus, ['paid', 'no_payment_required'], true)" => "Success reconciliation accepts terminal Stripe success statuses.",
];

foreach ($required as $marker => $description) {
    if (!str_contains($source, $marker)) {
        $failures[] = $description . " Missing marker: " . $marker;
    }
}

$stalePattern = "/catch \(Throwable(?: \$e)?\).*?return new Response\('', 303, \['Location' => \$checkoutUrl\]\);/s";
if (preg_match($stalePattern, $source) === 1) {
    $failures[] = 'Resume flow still redirects to a stored Stripe checkout URL from a Throwable catch block.';
}

$tailPattern = "/if \(\$checkoutUrl !== ''\) \{\s*return new Response\('', 303, \['Location' => \$checkoutUrl\]\);\s*\}\s*\$this->sales->releaseReservationsForOrder\(\$orderId, 'checkout_abandoned'\);/s";
if (preg_match($tailPattern, $source) === 1) {
    $failures[] = 'Resume flow still falls back to a stored Stripe checkout URL for unknown terminal states.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Stripe no-stale checkout resume static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Stripe no-stale checkout resume static check passed.\n";

// End of file.
