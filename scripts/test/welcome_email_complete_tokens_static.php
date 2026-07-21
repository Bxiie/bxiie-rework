<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Platform/Auth/SignupPostRegistrationMailer.php';
$source = is_file($path) ? (string) file_get_contents($path) : '';

$required = [
    "'tour_url' => \$siteUrl . '/admin/getting-started'",
    "'help_url' => \$platformUrl . '/help'",
    "'functions_url' => \$platformUrl . '/help/tenant-admin-functions'",
    "'videos_url' => \$platformUrl . '/help/training-videos'",
    "'tenant_admin_url' => \$adminUrl",
    "assertNoUnresolvedTokens(\$message['body'], 'lifecycle/welcome.md')",
    "Refusing to queue email with unresolved template tokens",
];

$missing = [];
foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        $missing[] = $needle;
    }
}

if ($missing !== []) {
    foreach ($missing as $needle) {
        fwrite(STDERR, "[FAIL] SignupPostRegistrationMailer.php missing: {$needle}\n");
    }
    exit(1);
}

$template = (string) file_get_contents($root . '/template/email/lifecycle/welcome.md');
foreach (['tour_url', 'help_url', 'functions_url', 'videos_url'] as $token) {
    if (!str_contains($template, '{{ ' . $token . ' }}') && !str_contains($template, '{{' . $token . '}}')) {
        fwrite(STDERR, "[FAIL] Welcome template no longer contains expected token: {$token}\n");
        exit(1);
    }
}

echo "[PASS] Signup welcome email supplies all documented navigation tokens and rejects unresolved tokens.\n";
