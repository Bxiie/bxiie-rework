<?php

/**
 * Regression test for multi Set-Cookie session headers.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

use App\Http\Response;
use App\Http\Support\SessionCookie;

$_SERVER['HTTP_HOST'] = 'bxiie.artsfol.io';
$_SERVER['HTTPS'] = 'on';

$headers = SessionCookie::issueSetCookie('abc123', true);
if (count($headers) < 2) {
    fwrite(STDERR, "Expected stale-cookie clearing plus active session Set-Cookie headers.\n");
    exit(1);
}
if (!str_contains(implode("\n", $headers), 'Domain=.artsfol.io')) {
    fwrite(STDERR, "Expected artsfol.io domain cookie header.\n");
    exit(1);
}

$response = new Response('', 302, ['Location' => '/admin', 'Set-Cookie' => $headers]);
$ref = new ReflectionClass($response);
$prop = $ref->getProperty('headers');
$prop->setAccessible(true);
$responseHeaders = $prop->getValue($response);

if (!is_array($responseHeaders['Set-Cookie'] ?? null)) {
    fwrite(STDERR, "Response did not preserve multiple Set-Cookie values.\n");
    exit(1);
}

echo "Auth cookie headers support stale clearing and repeated Set-Cookie output.\n";

// End of file.
