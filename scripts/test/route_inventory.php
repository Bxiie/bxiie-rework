<?php

declare(strict_types=1);

/**
 * Static route inventory regression test.
 *
 * This test guards the intended platform/tenant route split without needing a
 * browser or network listener. HTTP-level smoke coverage remains in
 * scripts/test/http_smoke.sh.
 */

$root = dirname(__DIR__, 2);
$indexFile = $root . '/public/index.php';

$contents = file_get_contents($indexFile);

if ($contents === false) {
    fwrite(STDERR, "Could not read {$indexFile}\n");
    exit(1);
}

function failWith(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!str_contains($contents, "if (\$tenant) {")) {
    failWith('Tenant route block not found.');
}

$parts = explode('if ($tenant) {', $contents, 2);
$beforeTenant = $parts[0];
$afterTenant = $parts[1];

$tenantBlock = explode('exit;', $afterTenant, 2)[0] ?? '';
$platformBlock = explode('exit;', $afterTenant, 2)[1] ?? '';

$tenantMustContain = [
    "\$router->get('/'",
    "\$router->get('/login'",
    "\$router->post('/login'",
    "\$router->get('/admin'",
    "\$router->get('/contact'",
    "\$router->post('/signup'",
];

foreach ($tenantMustContain as $needle) {
    if (!str_contains($tenantBlock, $needle)) {
        failWith("Tenant route block is missing expected route fragment: {$needle}");
    }
}

$tenantMustNotContain = [
    "\$router->get('/admin/jobs'",
    "\$router->get('/admin/workers'",
    "\$router->get('/admin/platform-settings'",
];

foreach ($tenantMustNotContain as $needle) {
    if (str_contains($tenantBlock, $needle)) {
        failWith("Tenant route block unexpectedly contains platform route fragment: {$needle}");
    }
}

$platformMustContain = [
    "\$router->get('/'",
    "\$router->get('/login'",
    "\$router->get('/admin'",
    "\$router->get('/admin/jobs'",
    "\$router->get('/admin/workers'",
    "\$router->get('/admin/platform-settings'",
];

foreach ($platformMustContain as $needle) {
    if (!str_contains($platformBlock, $needle) && !str_contains($beforeTenant, $needle)) {
        failWith("Platform route area is missing expected route fragment: {$needle}");
    }
}

if (
    !str_contains($platformBlock, "\$router->post('/login'")
    && !str_contains($beforeTenant, "\$router->post('/login'")
    && !str_contains($platformBlock, "\$router->post('/login/password'")
    && !str_contains($beforeTenant, "\$router->post('/login/password'")
) {
    failWith("Platform route area is missing an expected login POST route.");
}

if (str_contains($tenantBlock, "\$router->get('/signup'")) {
    failWith('Tenant GET /signup exists unexpectedly. Signup form is currently embedded on tenant home and submitted with POST /signup.');
}

echo json_encode([
    'ok' => true,
    'tenant_login' => 'domain/login',
    'tenant_root' => 'public tenant site',
    'platform_jobs_route_is_platform_only' => true,
    'platform_workers_route_is_platform_only' => true,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
