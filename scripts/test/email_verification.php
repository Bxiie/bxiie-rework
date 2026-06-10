<?php

declare(strict_types=1);

/**
 * Manual verification script for local account email verification token creation and consumption.
 */

use App\Platform\Auth\Email\EmailVerificationService;
use App\Platform\Auth\Email\EmailVerificationTokenRepository;
use App\Platform\Auth\Password\PasswordAuthService;
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

$email = 'email-verification-test@example.test';

if (!$users->findByEmail($email)) {
    $auth->register(
        email: $email,
        password: 'email-verification-password',
        displayName: 'Email Verification Test User',
        emailVerified: false,
    );
}

$service = new EmailVerificationService(
    pdo: $pdo,
    users: $users,
    tokens: new EmailVerificationTokenRepository($pdo),
);

$verification = $service->createVerificationTokenForEmail($email);

if (!$verification) {
    fwrite(STDERR, "Failed to create verification token.\n");
    exit(1);
}

$result = $service->verifyEmail($verification['verification_token']);

$stmt = $pdo->prepare(
    "SELECT verified_at
     FROM user_identities
     WHERE user_id = :user_id
       AND email = :email
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->execute([
    'user_id' => $result['user_id'],
    'email' => $email,
]);

$identity = $stmt->fetch();

echo json_encode([
    'verified_user_id' => $result['user_id'],
    'email' => $result['email'],
    'token_length' => strlen($verification['verification_token']),
    'token_hash_length' => strlen($verification['token_hash']),
    'verified_at_present' => !empty($identity['verified_at']),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
