<?php

declare(strict_types=1);

/**
 * Manual verification script for global identity and tenant membership foundations.
 */

use App\Platform\Identity\PasswordHasher;
use App\Platform\Identity\UserIdentityRepository;
use App\Platform\Identity\UserRepository;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Membership\MembershipService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$userRepository = new UserRepository($pdo);
$identityRepository = new UserIdentityRepository($pdo);
$membershipRepository = new MembershipRepository($pdo);
$membershipService = new MembershipService($membershipRepository);
$passwordHasher = new PasswordHasher();

$email = 'identity-test@example.test';
$user = $userRepository->findByEmail($email);

if (!$user) {
    $userId = $userRepository->create($email, 'Identity Test User', $passwordHasher->hash('local-test-password'));
    $identityRepository->addLocalPasswordIdentity($userId, $email, true);
} else {
    $userId = (int) $user['id'];
}

$stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = 'bxiie' LIMIT 1");
$stmt->execute();
$tenant = $stmt->fetch();

if (!$tenant) {
    fwrite(STDERR, "Missing bxiie tenant.\n");
    exit(1);
}

$tenantId = (int) $tenant['id'];
$membershipService->addTenantOwner($tenantId, $userId);
$user = $userRepository->findById($userId);

echo json_encode([
    'user_id' => $userId,
    'email' => $email,
    'tenant_id' => $tenantId,
    'tenant_roles' => $membershipRepository->tenantRolesForUser($tenantId, $userId),
    'password_verify' => $passwordHasher->verify('local-test-password', (string) $user['password_hash']),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
