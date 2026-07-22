#!/usr/bin/php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Platform/Monitoring/OperationsMonitor.php';
$body = file_get_contents($path);

if ($body === false) {
    fwrite(STDERR, "[FAIL] Unable to read OperationsMonitor.php\n");
    exit(1);
}

$required = [
    'v2 marker' => 'ARTSFOLIO_REBOOT_REQUIRED_REASON_V2',
    'run reboot marker' => '/run/reboot-required',
    'legacy reboot marker' => '/var/run/reboot-required',
    'package reason source' => 'reboot-required.pkgs',
    'detail helper call' => 'rebootRequiredReason()',
    'specific package wording' => 'requires a reboot to finish updating',
    'missing package fallback' => 'did not identify a package',
];

foreach ($required as $label => $needle) {
    if (!str_contains($body, $needle)) {
        fwrite(STDERR, "[FAIL] Missing {$label}: {$needle}\n");
        exit(1);
    }
}

echo "[PASS] Reboot-required metrics include a package-level reason.\n";

// End of file.
