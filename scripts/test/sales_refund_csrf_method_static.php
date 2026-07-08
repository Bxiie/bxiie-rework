<?php

declare(strict_types=1);

$controllerPath = __DIR__ . '/../../app/Http/Controllers/Tenant/Admin/SalesController.php';
$csrfPath = __DIR__ . '/../../app/Support/Security/CsrfTokenService.php';

$controller = file_get_contents($controllerPath);
$csrf = file_get_contents($csrfPath);

$problems = [];

if ($controller === false) {
    $problems[] = 'Could not read SalesController.php.';
}
if ($csrf === false) {
    $problems[] = 'Could not read CsrfTokenService.php.';
}

$expectedPostValidateSingle = <<<'MARKER'
->validate((string) ($_POST['csrf_token'] ?? ''))
MARKER;
$expectedPostValidateDouble = <<<'MARKER'
->validate((string) ($_POST["csrf_token"] ?? ""))
MARKER;

if ($controller !== false && str_contains($controller, '->verify(')) {
    $problems[] = 'SalesController calls nonexistent CsrfTokenService::verify().';
}
if ($controller !== false
    && !str_contains($controller, $expectedPostValidateSingle)
    && !str_contains($controller, $expectedPostValidateDouble)
) {
    $problems[] = 'SalesController does not validate posted CSRF tokens with CsrfTokenService::validate().';
}
if ($csrf !== false && !str_contains($csrf, 'function validate(?string $token): bool')) {
    $problems[] = 'CsrfTokenService::validate() signature was not found.';
}
if ($csrf !== false && str_contains($csrf, 'function verify(')) {
    $problems[] = 'Unexpected CsrfTokenService::verify() method found; keep CSRF API consistent on validate().';
}

if ($problems !== []) {
    fwrite(STDERR, "[FAIL] Sales refund CSRF method static check failed:\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}

echo "[PASS] Sales refund CSRF method static check passed.\n";

// End of file.
