<?php

declare(strict_types=1);

/**
 * Manual verification script for local password reset token creation and consumption.
 */

use App\Platform\Auth\Password\PasswordAuthService;
use App\Platform\Auth\Password\PasswordResetService;
use App\Platform\Auth\Password\PasswordResetTokenRepository;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Identity\UserIdentityRepository;
use App\Platform\Identity\UserRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$users = new UserRepository($pdo);
$identities = new UserIdentityRepository($pdo);
$passwords = new PasswordHasher();
$sessions = new SessionRepository($pdo);
$sessionTokens = new SessionTokenService();

$auth = new PasswordAuthService(
    users: $users,
    identities: $identities,
    passwords: $passwords,
    sessions: $sessions,
    tokens: $sessionTokens,
);

$email = 'password-reset-test@example.test';
$oldPassword = 'old-reset-password';
$newPassword = 'new-reset-password';

if (!$users->findByEmail($email)) {
    $auth->register(
        email: $email,
        password: $oldPassword,
        displayName: 'Password Reset Test User',
        emailVerified: true,
    );
}

$resetService = new PasswordResetService(
    pdo: $pdo,
    users: $users,
    passwords: $passwords,
    tokens: new PasswordResetTokenRepository($pdo),
);

$reset = $resetService->createResetTokenForEmail($email);

if (!$reset) {
    fwrite(STDERR, "Failed to create reset token.\n");
    exit(1);
}

$userId = $resetService->resetPassword($reset['reset_token'], $newPassword);

$login = $auth->login(
    email: $email,
    password: $newPassword,
    tenantId: null,
    ipAddress: '127.0.0.1',
    userAgent: 'manual-password-reset-test',
);

echo json_encode([
    'reset_user_id' => $userId,
    'login_user_id' => $login['user_id'],
    'token_length' => strlen($reset['reset_token']),
    'token_hash_length' => strlen($reset['token_hash']),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
