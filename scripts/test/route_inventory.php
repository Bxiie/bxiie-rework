<?php

declare(strict_types=1);

/**
 * Static route inventory regression test.
 *
 * This guards the intended platform/tenant route split without opening a
 * network listener. The parser intentionally extracts the full tenant if-block
 * with brace matching instead of splitting on the first exit; tenant guard code
 * legitimately contains early exits before route registration.
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

/**
 * Return the source slice covered by the first balanced brace block beginning
 * at the supplied opening brace offset.
 */
function extractBalancedBlock(string $source, int $openBraceOffset): string
{
    $length = strlen($source);
    $depth = 0;
    $inSingle = false;
    $inDouble = false;
    $escaped = false;

    for ($i = $openBraceOffset; $i < $length; $i++) {
        $char = $source[$i];

        if ($escaped) {
            $escaped = false;
            continue;
        }

        if (($inSingle || $inDouble) && $char === '\\') {
            $escaped = true;
            continue;
        }

        if (!$inDouble && $char === "'") {
            $inSingle = !$inSingle;
            continue;
        }

        if (!$inSingle && $char === '"') {
            $inDouble = !$inDouble;
            continue;
        }

        if ($inSingle || $inDouble) {
            continue;
        }

        if ($char === '{') {
            $depth++;
            continue;
        }

        if ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $openBraceOffset, $i - $openBraceOffset + 1);
            }
        }
    }

    failWith('Tenant route block opening brace was found, but no matching closing brace was found.');
}

$tenantNeedle = 'if ($tenant) {';
$tenantIfOffset = strpos($contents, $tenantNeedle);

if ($tenantIfOffset === false) {
    failWith('Tenant route block not found.');
}

$tenantOpenBraceOffset = strpos($contents, '{', $tenantIfOffset);
if ($tenantOpenBraceOffset === false) {
    failWith('Tenant route block opening brace not found.');
}

$beforeTenant = substr($contents, 0, $tenantIfOffset);
$tenantBlock = extractBalancedBlock($contents, $tenantOpenBraceOffset);
$platformBlock = substr($contents, $tenantIfOffset + strlen($tenantBlock));

$tenantMustContain = [
    "\$router->get('/'",
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
    failWith('Platform route area is missing an expected login POST route.');
}

if (!str_contains($tenantBlock, "\$router->get('/signup'")) {
    failWith('Tenant GET /signup is missing. Tenant signup must support GET /signup and POST /signup.');
}
if (!str_contains($tenantBlock, "\$router->post('/signup'")) {
    failWith('Tenant POST /signup is missing. Tenant signup must support GET /signup and POST /signup.');
}

echo json_encode([
    'ok' => true,
    'tenant_root_route_present' => true,
    'tenant_admin_routes_are_tenant_only' => true,
    'platform_jobs_route_is_platform_only' => true,
    'platform_workers_route_is_platform_only' => true,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
