<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    $root . '/public/index.php' => ['new AppKernel', 'Request::fromGlobals'],
    $root . '/app/Http/AppKernel.php' => ["Routes/tenant.php", "Routes/platform.php", 'TenantPasswordResetGuard'],
    $root . '/app/Http/Routes/tenant.php' => ['$router->get(\'/portfolio\'', '$router->get(\'/admin\'', '$router->get(\'/api/me\''],
    $root . '/app/Http/Routes/platform.php' => ['$router->get(\'/directory\'', '$router->get(\'/platform/admin\'', '$router->get(\'/api/me\''],
    $root . '/app/Http/Auth/TenantPasswordResetGuard.php' => ['recipientExists', 'tenant_memberships', 'tenant_users'],
];
foreach ($checks as $file => $needles) {
    $source = file_get_contents($file);
    if ($source === false) { fwrite(STDERR, "Missing file: $file
"); exit(1); }
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) { fwrite(STDERR, "Missing expected routing marker: $needle in $file
"); exit(1); }
    }
}
if (substr_count(file_get_contents($root . '/public/index.php') ?: '', '$router->') !== 0) {
    fwrite(STDERR, "public/index.php still contains route registrations.
"); exit(1);
}
$actual = shell_exec(PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/test/route_inventory.php'));
$expected = file_get_contents($root . '/scripts/test/fixtures/route_inventory.json');
if ($actual === null || $expected === false || trim($actual) !== trim($expected)) {
    fwrite(STDERR, "Route inventory differs from the committed snapshot.
"); exit(1);
}
$rows = json_decode($actual, true, 512, JSON_THROW_ON_ERROR);
$seen = [];
foreach ($rows as $row) {
    $key = $row['scope'] . ' ' . $row['method'] . ' ' . $row['path'];
    if (isset($seen[$key])) { fwrite(STDERR, "Duplicate route: $key
"); exit(1); }
    $seen[$key] = true;
}
echo "Phase 8 routing static checks passed.
";
