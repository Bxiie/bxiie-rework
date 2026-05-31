<?php

declare(strict_types=1);

use App\Http\Support\SessionCookie;

require __DIR__ . '/../../vendor/autoload.php';

$cookie = new SessionCookie(
    'artsfolio_session',
    true,
    'Lax',
    '/'
);

$expiresAt = new DateTimeImmutable('+1 hour');
$headers = $cookie->issueSetCookie('session-test-token', $expiresAt);

/*
 * SessionCookie currently returns one Set-Cookie header string.
 * Older versions returned an array. Normalize both shapes so this
 * regression test checks behavior instead of implementation plumbing.
 */
$headers = is_array($headers) ? $headers : [$headers];

if (count($headers) !== 1) {
    fwrite(STDERR, "Expected one Set-Cookie header.\n");
    exit(1);
}

$header = $headers[0];

$requiredFragments = [
    'artsfolio_session=session-test-token',
    'Path=/',
    'HttpOnly',
    'Secure',
    'SameSite=Lax',
];

foreach ($requiredFragments as $fragment) {
    if (!str_contains($header, $fragment)) {
        fwrite(STDERR, "Missing cookie header fragment: {$fragment}\nHeader: {$header}\n");
        exit(1);
    }
}

echo "Auth cookie header regression passed.\n";

// End of file.
