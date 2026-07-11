#!/usr/bin/php
<?php

/**
 * Regression checks for recipient-email expansion in signup invitations.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php';

if (!is_file($controllerPath)) {
    fwrite(STDERR, "[FAIL] Missing SignupCodesController.php\n");
    exit(1);
}

$controller = (string) file_get_contents($controllerPath);

$checks = [
    'editor-style recipient placeholder is mapped' =>
        str_contains($controller, "'{{ recipient_email }}' => $email"),
    'legacy uppercase recipient placeholder is mapped' =>
        str_contains($controller, "'{{RECIPIENT_EMAIL}}' => $email"),
    'recipient value comes from validated queueInvite email argument' =>
        str_contains($controller, 'private function queueInvite(string $email, array $signupCode): void'),
    'editor-style complimentary months placeholder is mapped' =>
        str_contains($controller, "'{{ free_access_months }}' => $monthsLabel"),
    'editor-style signup code placeholder is mapped' =>
        str_contains($controller, "'{{ signup_code }}' => $code"),
    'editor-style signup URL placeholder is mapped' =>
        str_contains($controller, "'{{ signup_url }}' => $signupUrl"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

$template = "{{ recipient_email }}|{{ free_access_months }}|{{ signup_code }}|{{ signup_url }}";
$rendered = strtr($template, [
    '{{ recipient_email }}' => 'prospect@example.com',
    '{{ free_access_months }}' => '6 months',
    '{{ signup_code }}' => 'ABC123',
    '{{ signup_url }}' => 'https://artsfol.io/signup?code=ABC123',
]);

if ($rendered !== 'prospect@example.com|6 months|ABC123|https://artsfol.io/signup?code=ABC123') {
    fwrite(STDERR, "[FAIL] Placeholder rendering behavior is incorrect.\n");
    exit(1);
}

echo "[PASS] Signup invite recipient-email placeholder check passed.\n";

// End of file.
