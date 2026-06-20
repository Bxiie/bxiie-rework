<?php

declare(strict_types=1);

/**
 * Static regression checks for tenant signup owner role repair and branded
 * signup failures.
 */

$servicePath = __DIR__ . '/../../app/Platform/Signup/TenantSignupService.php';
$signupPath = __DIR__ . '/../../app/Http/Controllers/Platform/SignupController.php';
$errorPagePath = __DIR__ . '/../../app/Http/View/ErrorPage.php';
$migrationPath = __DIR__ . '/../../database/migrations/0037_repair_tenant_roles.sql';

$files = [
    $servicePath => file_get_contents($servicePath),
    $signupPath => file_get_contents($signupPath),
    $errorPagePath => file_get_contents($errorPagePath),
    $migrationPath => file_get_contents($migrationPath),
];

foreach ($files as $path => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Could not read {$path}\n");
        exit(1);
    }
}

$checks = [
    'Signup self-heals tenant owner role' => [$files[$servicePath], 'function ensureTenantOwnerRoleId'],
    'Signup role lookup uses tenant scope' => [$files[$servicePath], "WHERE scope = 'tenant'"],
    'Signup role lookup uses role slug' => [$files[$servicePath], 'slug IN'],
    'Signup role assignment no longer fails on missing seed role' => [$files[$servicePath], 'Tenant owner role could not be created'],
    'Signup failure logs server detail' => [$files[$signupPath], "error_log('Tenant signup failed:"],
    'Signup failure uses branded error response' => [$files[$signupPath], 'return Response::error('],
    'Branded error page has signup status copy' => [$files[$errorPagePath], 'Could not create site'],
    'Role repair migration seeds tenant owner' => [$files[$migrationPath], "('tenant', 'owner', 'Tenant Owner'"],
];

foreach ($checks as $label => [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Failed signup owner role and branding static check: {$label}\n");
        exit(1);
    }
}

$forbiddenRawFragments = [
    "Response::html('<h1>Could not create site",
    "Response::html('<h1>Password too short",
    'No tenant owner/admin role exists for new tenant owner assignment.',
];

foreach ($forbiddenRawFragments as $fragment) {
    foreach ([$servicePath, $signupPath] as $path) {
        if (str_contains($files[$path], $fragment)) {
            fwrite(STDERR, "Found forbidden raw signup fragment in {$path}: {$fragment}\n");
            exit(1);
        }
    }
}

echo "Signup owner role and branding static checks passed.\n";

// End of file.
