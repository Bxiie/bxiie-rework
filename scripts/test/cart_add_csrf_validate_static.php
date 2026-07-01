<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$csrf = file_get_contents($root . '/app/Support/Security/CsrfTokenService.php') ?: '';
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';

foreach (['function validate(?string $token): bool', 'hash_equals'] as $needle) {
    if (!str_contains($csrf, $needle)) {
        $failures[] = "CsrfTokenService missing {$needle}";
    }
}

if (!str_contains($controller, "->validate((string) (\$_POST['csrf_token'] ?? ''))")) {
    $failures[] = 'SalesController does not use CsrfTokenService::validate() for cart CSRF checks.';
}

if (str_contains($controller, '->verify(')) {
    $failures[] = 'SalesController still calls missing CsrfTokenService::verify().';
}

if (!str_contains($controller, '[ArtsFolio cart/add]')) {
    $failures[] = 'SalesController missing cart/add failure log marker.';
}

if ($failures !== []) {
    fwrite(STDERR, "Cart add CSRF validate static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Cart add CSRF validate static checks passed.\n";

// End of file.
