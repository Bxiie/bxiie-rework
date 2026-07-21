<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Platform/Auth/SignupPostRegistrationMailer.php';
$source = is_file($path) ? (string) file_get_contents($path) : '';

$required = [
    '$bodyWithoutRendererTokens = str_replace(',
    "'{{logo}}'",
    "'{{logo-small}}'",
    "'{{logo-large}}'",
    "preg_match_all('/\\{\\{\\s*[A-Za-z0-9_.-]+\\s*\\}\\}/', \$bodyWithoutRendererTokens",
];
foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "[FAIL] SignupPostRegistrationMailer.php missing: {$needle}\n");
        exit(1);
    }
}

if (!str_contains((string) file_get_contents($root . '/template/email/auth/email-verification-request.md'), '{{logo}}')) {
    fwrite(STDERR, "[FAIL] Verification template no longer exercises the branded logo token.\n");
    exit(1);
}

echo "[PASS] Signup mail validation permits renderer-owned logo tokens while retaining unresolved-token checks.\n";
