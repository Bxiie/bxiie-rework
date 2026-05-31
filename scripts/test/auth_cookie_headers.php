<?php

/**
 * Regression checks for browser auth cookie header behavior.
 */

declare(strict_types=1);

use App\Http\Support\SessionCookie;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$_SERVER['HTTP_HOST'] = 'bxiie.artsfol.io';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

$headers = SessionCookie::issueHeaders('test-token', true);
if (count($headers) < 2) {
    fwrite(STDERR, "Expected login to emit multiple Set-Cookie headers.\n");
    exit(1);
}

$hasDomainSet = false;
$hasHostExpire = false;
foreach ($headers as $header) {
    if (str_contains($header, 'artsfolio_session=test-token') && str_contains($header, 'Domain=.artsfol.io')) {
        $hasDomainSet = true;
    }
    if (str_contains($header, 'artsfolio_session=deleted') && !str_contains($header, 'Domain=')) {
        $hasHostExpire = true;
    }
}

if (!$hasDomainSet || !$hasHostExpire) {
    fwrite(STDERR, "Cookie headers did not include both stale host-cookie cleanup and domain session set.\n");
    fwrite(STDERR, implode("\n", $headers) . "\n");
    exit(1);
}

$pdo = Database::connect($root);
$sessions = new SessionRepository($pdo);
$tokens = new SessionTokenService();
$hash = $tokens->hashToken('cookie-header-test-' . bin2hex(random_bytes(8)));

$stmt = $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1');
$user = $stmt ? $stmt->fetch() : false;
if (!$user) {
    fwrite(STDERR, "No users exist; run seed or password_auth.php first.\n");
    exit(1);
}

$sessionId = $sessions->create($hash, (int) $user['id'], null, '127.0.0.1', 'auth-cookie-test', 300);
$session = $sessions->findActiveByHash($hash);
if (!$session || (int) $session['id'] !== $sessionId) {
    fwrite(STDERR, "Session repository failed create/read regression.\n");
    exit(1);
}

$sessions->revokeByHash($hash);
echo "Auth cookie headers and session repository regression checks passed.\n";

// End of file.
