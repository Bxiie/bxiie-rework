<?php

declare(strict_types=1);

/**
 * Regression test for platform signup redirect environment handling.
 */

$root = dirname(__DIR__, 2);
$controllerFile = $root . '/app/Http/Controllers/Platform/SignupController.php';

$contents = file_get_contents($controllerFile);

if ($contents === false) {
    fwrite(STDERR, "Could not read SignupController.php.\n");
    exit(1);
}

$required = [
    'private function loginUrl(string $domain): string',
    "getenv('APP_ENV')",
    "getenv('ARTSFOLIO_LOCAL_DEV_PORT')",
    "return 'http://' . \$domain . \$port . '/login';",
    "return 'https://' . \$domain . '/login';",
];

foreach ($required as $needle) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Signup redirect logic missing expected fragment: {$needle}\n");
        exit(1);
    }
}

echo "Platform signup redirect smoke test passed.\n";

// End of file.
