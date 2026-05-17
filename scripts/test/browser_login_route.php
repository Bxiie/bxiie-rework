<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$index = file_get_contents($root . '/public/index.php');

if ($index === false || !str_contains($index, "/login") || !str_contains($index, "LoginController")) {
    fwrite(STDERR, "Login route/controller is not wired in public/index.php.\n");
    exit(1);
}

echo "Browser login route smoke test passed.\n";

// End of file.
