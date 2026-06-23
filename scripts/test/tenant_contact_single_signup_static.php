<?php

declare(strict_types=1);

/**
 * Guards against rendering two independent email-list signup forms on Contact.
 */

$controllerPath = __DIR__ . '/../../app/Http/Controllers/Tenant/HomeController.php';
$controller = file_get_contents($controllerPath);
if ($controller === false) {
    fwrite(STDERR, "Unable to read {$controllerPath}.\n");
    exit(1);
}

$contactStart = strpos($controller, 'public function contact(');
$nextMethod = $contactStart === false ? false : strpos($controller, '    private function ', $contactStart);
if ($contactStart === false || $nextMethod === false) {
    fwrite(STDERR, "Unable to isolate the tenant contact action.\n");
    exit(1);
}

$contactAction = substr($controller, $contactStart, $nextMethod - $contactStart);
$failures = [];

if (str_contains($contactAction, 'action="/signup"')) {
    $failures[] = 'Contact page still renders a standalone email-list signup form.';
}

if (str_contains($contactAction, 'data-af-result="signup-form-result"')) {
    $failures[] = 'Contact page still contains the redundant signup-form result block.';
}

if (str_contains($contactAction, '$signupCaptcha')) {
    $failures[] = 'Contact action still builds an unused signup CAPTCHA.';
}

if (!str_contains($contactAction, 'name="join_email_list"')) {
    $failures[] = 'Contact form no longer offers the intentional email-list opt-in checkbox.';
}

if (!str_contains($controller, 'class="tenant-footer-signup"')) {
    $failures[] = 'Shared footer email signup form is missing.';
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant contact single-signup static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Tenant contact single-signup static checks passed.\n";

// End of file.