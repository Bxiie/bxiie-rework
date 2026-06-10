<?php

/**
 * Regression check: browser session lookup must not require users.status.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Platform/Auth/Session/SessionRepository.php';
$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "Could not read {$path}.\n");
    exit(1);
}

$forbiddenFragments = [
    'u.status',
    'user_status',
    'COALESCE(u.status',
];

foreach ($forbiddenFragments as $fragment) {
    if (str_contains($source, $fragment)) {
        fwrite(STDERR, "SessionRepository still depends on schema-drifted users.status fragment: {$fragment}\n");
        exit(1);
    }
}

echo "SessionRepository no longer depends on users.status.\n";

// End of file.
