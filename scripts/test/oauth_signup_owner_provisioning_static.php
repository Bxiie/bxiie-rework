<?php

declare(strict_types=1);

/**
 * Static regression checks for OAuth-created tenant owner provisioning.
 */

$servicePath = __DIR__ . '/../../app/Platform/Signup/TenantSignupService.php';
$signupPath = __DIR__ . '/../../app/Http/Controllers/Platform/SignupController.php';
$oauthPath = __DIR__ . '/../../app/Http/Controllers/Auth/OAuthController.php';

$service = file_get_contents($servicePath);
$signup = file_get_contents($signupPath);
$oauth = file_get_contents($oauthPath);

foreach ([
    $servicePath => $service,
    $signupPath => $signup,
    $oauthPath => $oauth,
] as $path => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Could not read {$path}\n");
        exit(1);
    }
}

$checks = [
    'TenantSignupService accepts existing OAuth user id' => [$service, '?int $existingUserId = null'],
    'TenantSignupService can skip password identity for OAuth signup' => [$service, 'bool $createPasswordIdentity = true'],
    'TenantSignupService updates existing OAuth user' => [$service, 'function updateExistingUser'],
    'Tenant membership insert is idempotent' => [$service, 'ON DUPLICATE KEY UPDATE'],
    'Tenant role assignment is idempotent' => [$service, 'INSERT IGNORE INTO role_assignments'],
    'Missing tenant owner role fails closed' => [$service, 'No tenant owner/admin role exists'],
    'SignupController reads OAuth user id from session' => [$signup, 'artsfolio_oauth_user_id'],
    'SignupController passes OAuth user id to signup service' => [$signup, 'existingUserId: $oauthUserId'],
    'SignupController skips password identity for OAuth signup' => [$signup, 'createPasswordIdentity: $oauthProfile === null'],
    'SignupController creates tenant-scoped session' => [$signup, "createBrowserSession((int) \$result['user_id'], (int) \$result['tenant_id'])"],
    'OAuthController stores OAuth user id for signup' => [$oauth, 'artsfolio_oauth_user_id'],
];

foreach ($checks as $label => [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Failed OAuth signup owner provisioning static check: {$label}\n");
        exit(1);
    }
}

echo "OAuth signup owner provisioning static checks passed.\n";

// End of file.
