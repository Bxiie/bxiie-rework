<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$users = (string) file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/UsersController.php');
$reset = (string) file_get_contents($root . '/app/Platform/Auth/Password/PasswordResetService.php');
$routes = (string) file_get_contents($root . '/app/Http/Routes/tenant.php');
$failures = [];

foreach ([
    'createInvitationTokenForTenantEmail',
    'Set your password and activate your access here:',
    'Set your password and activate access',
    'tenant-user-actions-row',
    'tenant-user-actions',
    'colspan="6"',
    'display: flex',
    'flex-wrap: wrap',
] as $marker) {
    if (!str_contains($users, $marker)) {
        $failures[] = "UsersController missing marker: {$marker}";
    }
}

foreach ([
    'public function createInvitationTokenForTenantEmail',
    "['active', 'invited']",
    "SET status = 'active'",
    "AND status = 'invited'",
    "array \$statuses = ['active']",
] as $marker) {
    if (!str_contains($reset, $marker)) {
        $failures[] = "PasswordResetService missing marker: {$marker}";
    }
}

$serviceMarker = 'new PasswordResetService($pdo, new UserRepository($pdo), new PasswordHasher(), new PasswordResetTokenRepository($pdo))';
$tenantUserRoutes = array_values(array_filter(
    preg_split('/\R/', $routes) ?: [],
    static fn (string $line): bool => str_contains(
        $line,
        'new TenantAdminUsersController(',
    ),
));

if ($tenantUserRoutes === []) {
    $failures[] = 'No Tenant Users routes were found.';
} else {
    foreach ($tenantUserRoutes as $routeLine) {
        if (!str_contains($routeLine, $serviceMarker)) {
            $failures[] = 'A Tenant Users route does not supply PasswordResetService: '
                . trim($routeLine);
        }
    }
}

foreach (['Open tenant login', 'use the password reset flow to set one', '<th>Password</th>'] as $stale) {
    if (str_contains($users, $stale)) {
        $failures[] = "Stale marker remains: {$stale}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant invite password and users layout check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Tenant invites open password setup and user actions are horizontal.\n";

// End of file.
