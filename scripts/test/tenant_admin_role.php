<?php

declare(strict_types=1);

/**
 * Manual verification script for assigning and checking tenant admin browser role access.
 */

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Membership\MembershipService;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));

$pdo = Database::connect($root);

$userStmt = $pdo->prepare("SELECT id, email FROM users WHERE email = 'password-auth-test@example.test' LIMIT 1");
$userStmt->execute();
$user = $userStmt->fetch();

if (!$user) {
    fwrite(STDERR, "Missing password-auth-test@example.test; bootstrapping with scripts/test/password_auth.php.\n");
    passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/test/password_auth.php'), $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "Could not bootstrap password-auth-test@example.test.\n");
        exit(1);
    }

    $userStmt->execute();
    $user = $userStmt->fetch();

    if (!$user) {
        fwrite(STDERR, "Missing password-auth-test@example.test after bootstrap.\n");
        exit(1);
    }
}

$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "Missing bxiie tenant resolution.\n");
    exit(1);
}

$memberships = new MembershipRepository($pdo);
$service = new MembershipService($memberships);
$service->addTenantAdmin($tenant->tenantId, (int) $user['id']);

$middleware = new RequireTenantRoleBrowser($memberships);

$result = [
    'user_id' => (int) $user['id'],
    'email' => $user['email'],
    'tenant_id' => $tenant->tenantId,
    'tenant_admin_allowed' => $middleware->allows(
        ['user_id' => (int) $user['id']],
        $tenant,
        [Roles::TENANT_OWNER, Roles::TENANT_ADMIN],
    ),
    'tenant_viewer_only_allowed' => $middleware->allows(
        ['user_id' => (int) $user['id']],
        $tenant,
        [Roles::TENANT_VIEWER],
    ),
];

if ($result['tenant_admin_allowed'] !== true) {
    fwrite(STDERR, "Expected tenant admin role to be allowed.\n");
    exit(1);
}

if ($result['tenant_viewer_only_allowed'] !== false) {
    fwrite(STDERR, "Expected viewer-only route to deny admin when viewer is not assigned.\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
