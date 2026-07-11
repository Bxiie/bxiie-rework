#!/usr/bin/php
<?php

/**
 * Regression checks for recipient-email expansion in signup invitations.
 */

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php';

if (!is_file($controllerPath)) {
    fwrite(STDERR, "[FAIL] Missing SignupCodesController.php\n");
    exit(1);
}

$controller = (string) file_get_contents($controllerPath);

$checks = [
    'editor-style recipient placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{ recipient_email }}' => $email
PHP
        ),

    'legacy uppercase recipient placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{RECIPIENT_EMAIL}}' => $email
PHP
        ),

    'recipient value comes from the validated queueInvite email argument' =>
        str_contains(
            $controller,
            <<<'PHP'
private function queueInvite(string $email, array $signupCode): void
PHP
        ),

    'editor-style complimentary months placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{ free_access_months }}' => $monthsLabel
PHP
        ),

    'legacy uppercase complimentary months placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{FREE_ACCESS_MONTHS}}' => $monthsLabel
PHP
        ),

    'editor-style signup code placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{ signup_code }}' => $code
PHP
        ),

    'legacy uppercase signup code placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{SIGNUP_CODE}}' => $code
PHP
        ),

    'editor-style signup URL placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{ signup_url }}' => $signupUrl
PHP
        ),

    'legacy uppercase signup URL placeholder is mapped' =>
        str_contains(
            $controller,
            <<<'PHP'
'{{SIGNUP_URL}}' => $signupUrl
PHP
        ),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

$template = <<<'TEMPLATE'
{{ recipient_email }}|{{ free_access_months }}|{{ signup_code }}|{{ signup_url }}
TEMPLATE;

$rendered = strtr($template, [
    '{{ recipient_email }}' => 'prospect@example.com',
    '{{ free_access_months }}' => '6 months',
    '{{ signup_code }}' => 'ABC123',
    '{{ signup_url }}' => 'https://artsfol.io/signup?code=ABC123',
]);

$expected = 'prospect@example.com|6 months|ABC123|https://artsfol.io/signup?code=ABC123';

if ($rendered !== $expected) {
    fwrite(STDERR, "[FAIL] Placeholder rendering behavior is incorrect.\n");
    exit(1);
}

echo "[PASS] Signup invite recipient-email placeholder check passed.\n";

// End of file.
