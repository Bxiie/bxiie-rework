<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$deploy = (string) file_get_contents($root . '/scripts/deploy/deploy_production.sh');

$checks = [
    'deploy creates a unique lint log with mktemp' => str_contains($deploy, 'LINT_LOG="$(mktemp /tmp/artsfolio-php-lint.XXXXXX)"'),
    'deploy writes PHP lint output to the unique lint log' => str_contains($deploy, '> "$LINT_LOG"'),
    'deploy removes the lint log after use' => str_contains($deploy, 'rm -f "$LINT_LOG"'),
    'deploy no longer writes the fixed shared lint path' => !str_contains($deploy, '> /tmp/artsfolio-php-lint.log'),
];

$failures = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Deploy temporary-file regression failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Deploy temporary-file regression passed.\n";

// End of file.
