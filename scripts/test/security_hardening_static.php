<?php

/**
 * Static regression checks for the July 2026 security hardening pass.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Controllers/Api/AdminApiController.php' => ['tenantAuthorized(\'tenant:write\', $tenantId)', '$tokenTenantId === $tenantId'],
    'app/Http/Controllers/Platform/StripeWebhookController.php' => ['$secret === \'\'', 'webhook_not_configured'],
    'app/Http/Controllers/Auth/LoginController.php' => ['auth:tenant-login:', 'Too many sign-in attempts'],
    'app/Http/Controllers/Auth/PasswordAuthController.php' => ['auth:platform-login:', 'Retry-After'],
    'app/Http/Routes/platform.php' => ['auth:password-forgot:', 'new RateLimiter($pdo)'],
    'app/Http/Routes/tenant.php' => ['auth:password-forgot:', 'new RateLimiter($pdo)', '$pdo, $csrf'],
    'app/Http/Controllers/Platform/CaddyAskController.php' => ['apcu_fetch', 'caddy:ask:'],
    'app/Http/Controllers/Tenant/MediaController.php' => ['storage/cache/watermarks/', 'HTTP_IF_NONE_MATCH'],
    'app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php' => ['name="csrf_token"', '$this->csrf->validate'],
    'app/Http/Controllers/Platform/Admin/SettingsController.php' => ['Configured; leave blank to keep', 'if ($stripeSecretKey !== \'\')'],
    'app/Platform/Signup/TenantSignupService.php' => ['private function normalizeSlug', "'/^[a-z0-9]"],
    'app/Platform/Tenancy/TenantStoragePaths.php' => ['Invalid tenant slug for storage path construction'],
    'docker-compose.yml' => ['127.0.0.1:3307:3306'],
    'bootstrap/app.php' => ["ini_set('display_errors', '0')"],
];

$errors = [];
foreach ($checks as $relative => $markers) {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        $errors[] = 'Missing file: ' . $relative;
        continue;
    }
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $relative . ' missing marker: ' . $marker;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Security hardening static check failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "[PASS] Security hardening static check passed.\n");

// End of file.
