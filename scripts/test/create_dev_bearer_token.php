<?php

declare(strict_types=1);

/**
 * Creates a development OAuth2 bearer token for manual API verification.
 */

use App\Platform\Auth\OAuth\BearerTokenService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$email = $argv[1] ?? 'password-auth-test@example.test';

$pdo = Database::connect($root);
$tokenService = new BearerTokenService();

$userStmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email LIMIT 1");
$userStmt->execute(['email' => strtolower(trim($email))]);
$user = $userStmt->fetch();

if (!$user) {
    fwrite(STDERR, "No user found for {$email}. Run php scripts/test/password_auth.php first.\n");
    exit(1);
}

$clientStmt = $pdo->prepare("SELECT id FROM oauth_clients WHERE client_identifier = 'artsfolio_dev_cli' LIMIT 1");
$clientStmt->execute();
$client = $clientStmt->fetch();

if (!$client) {
    $insertClient = $pdo->prepare(
        "INSERT INTO oauth_clients (
            uuid,
            client_name,
            client_identifier,
            client_type,
            redirect_uris,
            allowed_grant_types,
            allowed_scopes,
            status
        ) VALUES (
            UUID(),
            'ArtsFolio Development CLI',
            'artsfolio_dev_cli',
            'public',
            JSON_ARRAY(),
            JSON_ARRAY('development_token'),
            JSON_ARRAY('api:read'),
            'active'
        )"
    );

    $insertClient->execute();
    $clientId = (int) $pdo->lastInsertId();
} else {
    $clientId = (int) $client['id'];
}

$rawToken = $tokenService->generateDevelopmentToken();
$tokenHash = $tokenService->hashToken($rawToken);

$insertToken = $pdo->prepare(
    "INSERT INTO oauth_access_tokens (
        token_hash,
        client_id,
        user_id,
        tenant_id,
        scopes,
        expires_at
    ) VALUES (
        :token_hash,
        :client_id,
        :user_id,
        NULL,
        JSON_ARRAY('api:read'),
        DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY)
    )"
);

$insertToken->execute([
    'token_hash' => $tokenHash,
    'client_id' => $clientId,
    'user_id' => (int) $user['id'],
]);

echo json_encode([
    'access_token' => $rawToken,
    'token_hash' => $tokenHash,
    'user_id' => (int) $user['id'],
    'email' => (string) $user['email'],
    'expires_in' => 86400,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
