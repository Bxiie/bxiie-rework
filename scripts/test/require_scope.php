<?php

declare(strict_types=1);

/**
 * Manual verification script for OAuth2 bearer-token scope checks.
 */

use App\Http\Middleware\RequireScope;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$middleware = new RequireScope();

$token = [
    'scopes' => json_encode(['api:read'], JSON_THROW_ON_ERROR),
];

$result = [
    'api_read_allowed' => $middleware->hasScope($token, 'api:read'),
    'api_write_allowed' => $middleware->hasScope($token, 'api:write'),
    'missing_token_allowed' => $middleware->hasScope(null, 'api:read'),
];

if ($result['api_read_allowed'] !== true) {
    fwrite(STDERR, "Expected api:read to be allowed.\n");
    exit(1);
}

if ($result['api_write_allowed'] !== false) {
    fwrite(STDERR, "Expected api:write to be denied.\n");
    exit(1);
}

if ($result['missing_token_allowed'] !== false) {
    fwrite(STDERR, "Expected missing token to be denied.\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
