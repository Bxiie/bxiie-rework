#!/usr/bin/php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Platform/Auth/SignupPostRegistrationMailer.php');
$controller = (string) file_get_contents($root . '/app/Http/Controllers/Platform/SignupController.php');
$routes = (string) file_get_contents($root . '/app/Http/Routes/platform.php');
$recovery = (string) file_get_contents($root . '/scripts/admin/queue_signup_post_registration_email.php');
$welcome = (string) file_get_contents($root . '/template/email/lifecycle/welcome.md');

$checks = [
    'signup queues post-registration mail' => str_contains($controller, '$this->postRegistrationMailer->queueForEmail('),
    'routes inject mailer' => str_contains($routes, 'new SignupPostRegistrationMailer('),
    'verification message is queued' => str_contains($service, "'auth.email_verification_request'"),
    'welcome message is queued' => str_contains($service, "'lifecycle.welcome'"),
    'welcome includes admin URL' => str_contains($service, '$adminUrl = $siteUrl . \'/admin\''),
    'duplicate pending messages are suppressed' => str_contains($service, 'hasPending('),
    'recovery command exists' => str_contains($recovery, 'queueForEmail($email, $tenantSlug)'),
    'welcome template includes admin placeholder' => str_contains($welcome, '{{ admin_url }}'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}
echo "[PASS] Signup post-registration email static check passed.\n";

// End of file.
