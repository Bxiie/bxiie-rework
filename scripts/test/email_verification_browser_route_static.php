<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = (string) file_get_contents($root . '/app/Http/Routes/platform.php');
$controller = (string) file_get_contents($root . '/app/Http/Controllers/Auth/EmailVerificationController.php');
$service = (string) file_get_contents($root . '/app/Platform/Auth/Email/EmailVerificationService.php');

$checks = [
    'GET verification route' => str_contains($routes, '$router->get(\'/verify-email\''),
    'verification controller' => str_contains($routes, 'EmailVerificationController'),
    'token consumption' => str_contains($controller, 'verifyEmail($token)'),
    'users verification timestamp' => str_contains($service, 'email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP)'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

echo "[PASS] Email verification browser route static checks passed.\n";
