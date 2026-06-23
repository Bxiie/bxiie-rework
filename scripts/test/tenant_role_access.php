<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant role enforcement.
 */

use App\Http\Middleware\RequireTenantRole;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Membership\MembershipService;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));

$pdo = Database::connect($root);

$userStmt = $pdo->prepare("SELECT id FROM users WHERE email = 'password-auth-test@example.test' LIMIT 1");
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

$membershipRepository = new MembershipRepository($pdo);
$membershipService = new MembershipService($membershipRepository);
$membershipService->addTenantOwner($tenant->tenantId, (int) $user['id']);

$middleware = new RequireTenantRole($membershipRepository);

$result = [
    'owner_allowed' => $middleware->allows(
        accessToken: ['user_id' => (int) $user['id']],
        tenant: $tenant,
        allowedRoles: ['owner', 'admin'],
    ),
    'viewer_denied_when_owner_required' => $middleware->allows(
        accessToken: ['user_id' => (int) $user['id']],
        tenant: $tenant,
        allowedRoles: ['viewer'],
    ),
    'missing_token_denied' => $middleware->allows(
        accessToken: null,
        tenant: $tenant,
        allowedRoles: ['owner'],
    ),
];

if ($result['owner_allowed'] !== true) {
    fwrite(STDERR, "Expected owner/admin route to allow tenant owner.\n");
    exit(1);
}

if ($result['viewer_denied_when_owner_required'] !== false) {
    fwrite(STDERR, "Expected viewer-only route to deny owner when viewer role is not assigned.\n");
    exit(1);
}

if ($result['missing_token_denied'] !== false) {
    fwrite(STDERR, "Expected missing token to deny.\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
