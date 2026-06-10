<?php

/**
 * Static regression coverage for tenant email signup management and session bridge wiring.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'database/migrations/0032_email_signup_management_and_session_bridge.sql' => [
        'notes TEXT',
        'tenant_session_bridge_tickets',
    ],
    'app/Tenant/Signup/EmailSignupRepository.php' => [
        'searchForTenant',
        'countForTenant',
        'updateAdminFields',
        'function delete',
        'notes',
    ],
    'app/Http/Controllers/Tenant/Admin/EmailSignupsController.php' => [
        'function import',
        'function delete',
        'function update',
        'Search',
        'Import CSV',
    ],
    'app/Platform/Auth/Session/SessionBridgeRepository.php' => [
        'consumeTicket',
        'tenantOwnsHost',
    ],
    'app/Http/Controllers/Auth/TenantSessionBridgeController.php' => [
        'af_session_bridge',
        'customDomainBridgeRedirect',
    ],
    'public/index.php' => [
        '/auth/tenant-session/bridge',
        '/admin/email-signups/import',
        '/admin/email-signups/update',
    ],
    'app/Http/Controllers/Tenant/HomeController.php' => [
        'artsfolio_current_user',
        'tenant-admin-top-link',
    ],
];

$missing = [];
foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        $missing[] = $file . ' missing';
        continue;
    }
    $contents = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $missing[] = $file . ' missing marker ' . $needle;
        }
    }
}

if ($missing) {
    fwrite(STDERR, implode(PHP_EOL, $missing) . PHP_EOL);
    exit(1);
}

echo "Email signup management and tenant domain session bridge static checks passed.\n";

// End of file.
