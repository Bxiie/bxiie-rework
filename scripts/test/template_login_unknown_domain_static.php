<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$signup = (string) file_get_contents($root . '/app/Platform/Signup/TenantSignupService.php');
$login = (string) file_get_contents($root . '/app/Http/Controllers/Auth/LoginController.php');
$password = (string) file_get_contents($root . '/app/Http/Controllers/Auth/PasswordAuthController.php');
$kernel = (string) file_get_contents($root . '/app/Http/AppKernel.php');
$tenantRoutes = (string) file_get_contents($root . '/app/Http/Routes/tenant.php');
$queueScript = (string) file_get_contents($root . '/scripts/email/queue_lifecycle_emails.php');

$required = [
    [$signup, 'new EditableEmailTemplate('],
    [$signup, "'lifecycle/tenant_admin_feature_deep_dive_1d.txt'"],
    [$signup, "BrandedEmail::render(\$message['subject'], \$message['body'])"],
    [$login, 'PostLoginDestination'],
    [$login, '$this->destination->forUser'],
    [$password, '$this->destination->forUser'],
    [$kernel, "str_ends_with(\$requestHost, '.artsfol.io')"],
    [$kernel, "'Location' => 'https://artsfol.io/'"],
    [$tenantRoutes, 'new PostLoginDestination($pdo, new MembershipRepository($pdo))'],
    [$queueScript, 'new EditableEmailTemplate('],
];

foreach ($required as [$source, $marker]) {
    if (!str_contains($source, $marker)) {
        $failures[] = "Missing marker: {$marker}";
    }
}

foreach (['Your ArtsFolio your admin is here', 'Your your admin is here'] as $marker) {
    foreach ([$signup, $queueScript] as $source) {
        if (str_contains($source, $marker)) {
            $failures[] = "Hard-coded lifecycle copy remains: {$marker}";
        }
    }
}

foreach (glob($root . '/template/email/lifecycle/*.txt') ?: [] as $path) {
    $contents = (string) file_get_contents($path);
    if (!preg_match('/^Subject:\s*.+$/mi', $contents)) {
        $failures[] = 'Lifecycle template lacks Subject line: ' . basename($path);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Template/login/domain static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Templates, login destinations, and unknown-domain redirect are wired correctly.\n";

// End of file.
