<?php

declare(strict_types=1);

/**
 * Manual verification script for local email/password registration and login.
 */

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
$tokens = new SessionTokenService();

$auth = new PasswordAuthService(
    users: $users,
    identities: $identities,
    passwords: $passwords,
    sessions: $sessions,
    tokens: $tokens,
);

$email = 'password-auth-test@example.test';
$password = 'local-test-password';

$user = $users->findByEmail($email);

if (!$user) {
    $userId = $auth->register(
        email: $email,
        password: $password,
        displayName: 'Password Auth Test User',
        emailVerified: true,
    );
} else {
    $userId = (int) $user['id'];
}

$login = $auth->login(
    email: $email,
    password: $password,
    tenantId: null,
    ipAddress: '127.0.0.1',
    userAgent: 'manual-test',
);

$session = $sessions->findActiveByHash($login['session_hash']);

if (!$session) {
    fwrite(STDERR, "Session was not created or could not be read.\n");
    exit(1);
}

echo json_encode([
    'registered_or_existing_user_id' => $userId,
    'login_user_id' => $login['user_id'],
    'session_id' => $login['session_id'],
    'session_token_length' => strlen($login['session_token']),
    'session_lookup_email' => $session['email'],
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
