<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$files = [
    'mailer' => $root . '/app/Platform/Auth/SignupPostRegistrationMailer.php',
    'repository' => $root . '/app/Platform/Auth/Email/EmailVerificationTokenRepository.php',
    'service' => $root . '/app/Platform/Auth/Email/EmailVerificationService.php',
    'migration' => $root . '/database/migrations/0068_email_verification_tokens_tenant_scope.sql',
];
foreach ($files as $label => $path) {
    if (!is_file($path)) {
        $failures[] = "Missing {$label}: {$path}";
    }
}
if ($failures === []) {
    $mailer = (string) file_get_contents($files['mailer']);
    $repo = (string) file_get_contents($files['repository']);
    $service = (string) file_get_contents($files['service']);
    $migration = (string) file_get_contents($files['migration']);

    foreach ([
        'storeToken($userId, (int) ($tenant[\'id\'] ?? 0), $email, $rawToken)', 
        "tenant_id = :tenant_id",
        "A tenant is required for an email-verification token.",
    ] as $needle) {
        if (!str_contains($mailer, $needle)) {
            $failures[] = 'SignupPostRegistrationMailer.php missing: ' . $needle;
        }
    }
    foreach ([
        '?int $tenantId = null',
        'tenant_id IS NULL',
        'tenant_id = :tenant_id',
        ':tenant_id',
    ] as $needle) {
        if (!str_contains($repo, $needle)) {
            $failures[] = 'EmailVerificationTokenRepository.php missing: ' . $needle;
        }
    }
    if (!str_contains($service, 'tenantId: $tenantId')) {
        $failures[] = 'EmailVerificationService.php does not pass tenant scope to the repository.';
    }
    foreach (['ADD COLUMN tenant_id', 'REFERENCES tenants(id)', 'user_id, tenant_id, consumed_at, expires_at'] as $needle) {
        if (!str_contains($migration, $needle)) {
            $failures[] = 'Migration missing: ' . $needle;
        }
    }
    if (str_contains($mailer, 'DELETE FROM `{$table}` WHERE user_id = :user_id")->execute')) {
        $failures[] = 'Mailer still deletes every verification token for the user.';
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL] {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Email verification tokens are scoped per user and tenant.\n";
