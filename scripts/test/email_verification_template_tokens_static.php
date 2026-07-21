#!/usr/bin/php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Platform/Auth/SignupPostRegistrationMailer.php');

$checks = [
    'verification uses editable template renderer' => str_contains($service, "->render('auth/email-verification-request.md', \$values)"),
    'verification receives tenant context' => str_contains($service, 'findTenantContext('),
    'recipient name token is supplied' => str_contains($service, "'recipient_name' => \$recipientName"),
    'recipient email token is supplied' => str_contains($service, "'recipient_email' => \$email"),
    'tenant name token is supplied' => str_contains($service, "'tenant_name' => \$tenantName"),
    'tenant slug token is supplied' => str_contains($service, "'tenant_slug' => \$tenantSlug"),
    'verification URL token is supplied' => str_contains($service, "'verification_url' => \$url"),
    'branded text and HTML are generated together' => str_contains($service, 'BrandedEmail::render($subject, $message[\'body\'])'),
    'outbox receives recipient name' => str_contains($service, "recipientName: \$values['recipient_name']"),
    'outbox receives tenant id' => str_contains($service, "tenantId: (\$tenant['id'] ?? 0) > 0"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

echo "[PASS] Verification email template token static check passed.\n";
