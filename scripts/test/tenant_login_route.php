<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$index = file_get_contents($root . '/public/index.php');

if ($index === false) {
    fwrite(STDERR, "Could not read public/index.php.\n");
    exit(1);
}

$tenantPart = explode('if ($tenant) {', $index, 2)[1] ?? '';

if (!str_contains($tenantPart, "/login") || !str_contains($tenantPart, "LoginController")) {
    fwrite(STDERR, "Tenant login route is not wired.\n");
    exit(1);
}

echo "Tenant login route smoke test passed.\n";

// End of file.
