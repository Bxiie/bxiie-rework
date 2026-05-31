<?php

/**
 * Static regression checks for tenant login cookies and tenant admin invites.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Support/SessionCookie.php' => [
        'public static function issueSetCookie',
        'public static function loginHeaders',
        'public static function logoutHeaders',
    ],
    'app/Http/Controllers/Auth/LoginController.php' => [
        'login(Request $request, ?TenantContext $tenant = null)',
        'tenantId: $tenant?->tenantId',
        "'Set-Cookie' => SessionCookie::loginHeaders",
    ],
    'app/Http/Controllers/Tenant/Admin/UsersController.php' => [
        'public function invite(',
        'inviteTenantAdmin(',
        'tenant_admin_invite',
    ],
    'app/Platform/Identity/AdminUserRepository.php' => [
        'public function inviteTenantAdmin(',
        'INSERT INTO tenant_memberships',
        'INSERT IGNORE INTO role_assignments',
    ],
    'app/Http/Controllers/Platform/Admin/TenantsController.php' => [
        'Open tenant site in new tab',
        'publicUrlForTenant',
    ],
    'public/index.php' => [
        "->login($request, $tenant)",
        "/admin/users/invite",
        'new EmailOutboxRepository($pdo)',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            fwrite(STDERR, "Missing {$needle} in {$file}\n");
            exit(1);
        }
    }
}

echo "Tenant login and invite static checks passed.\n";

// End of file.
