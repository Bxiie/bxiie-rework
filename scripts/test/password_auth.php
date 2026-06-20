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

function cleanupPasswordAuthFixture(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($userIds === []) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($userIds), '?'));

    foreach ([
        'audit_log',
        'email_outbox',
        'role_assignments',
        'platform_roles',
        'tenant_memberships',
        'tenant_users',
        'user_sessions',
        'user_identities',
        'oauth_access_tokens',
        'password_reset_tokens',
        'email_verification_tokens',
    ] as $table) {
        if (!tableExists($pdo, $table)) {
            continue;
        }

        if (!columnExists($pdo, $table, 'user_id')) {
            continue;
        }

        $delete = $pdo->prepare("DELETE FROM {$table} WHERE user_id IN ({$placeholders})");
        $delete->execute($userIds);
    }

    $deleteUsers = $pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})");
    $deleteUsers->execute($userIds);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table'
    );
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column'
    );
    $stmt->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}


$email = 'password-auth-test@example.test';
$password = 'local-test-password';

cleanupPasswordAuthFixture($pdo, $email);

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
