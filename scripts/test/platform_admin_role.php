<?php

declare(strict_types=1);

/**
 * Manual verification script for assigning and checking a platform admin role.
 */

use App\Http\Middleware\RequirePlatformRole;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Membership\Roles;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

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

$memberships = new MembershipRepository($pdo);
$memberships->assignRole(Roles::PLATFORM_SCOPE, Roles::PLATFORM_ADMIN, (int) $user['id'], null);

$middleware = new RequirePlatformRole($memberships);

$result = [
    'user_id' => (int) $user['id'],
    'email' => $user['email'],
    'platform_admin_allowed' => $middleware->allows(
        ['user_id' => (int) $user['id']],
        [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN],
    ),
    'platform_viewer_allowed_for_admin_route' => $middleware->allows(
        ['user_id' => (int) $user['id']],
        [Roles::PLATFORM_READONLY],
    ),
];

if ($result['platform_admin_allowed'] !== true) {
    fwrite(STDERR, "Expected platform admin role to be allowed.\n");
    exit(1);
}

if ($result['platform_viewer_allowed_for_admin_route'] !== false) {
    fwrite(STDERR, "Expected readonly-only route to deny admin when readonly is not assigned.\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
