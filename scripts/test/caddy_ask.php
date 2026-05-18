<?php

declare(strict_types=1);

/**
 * Smoke test for app-backed Caddy on-demand TLS ask endpoint.
 */

$root = dirname(__DIR__, 2);

$controller = $root . '/app/Http/Controllers/Platform/CaddyAskController.php';
$index = $root . '/public/index.php';

foreach ([$controller, $index] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        exit(1);
    }
}

$controllerText = file_get_contents($controller);
$indexText = file_get_contents($index);

if ($controllerText === false || $indexText === false) {
    fwrite(STDERR, "Could not read Caddy ask files.\n");
    exit(1);
}

foreach ([
    'Caddy on-demand TLS authorization endpoint',
    'tenant_domains',
    "status = 'active'",
    'looksLikeHostname',
] as $needle) {
    if (!str_contains($controllerText, $needle)) {
        fwrite(STDERR, "Caddy ask controller missing expected fragment: {$needle}\n");
        exit(1);
    }
}

foreach ([
    'CaddyAskController',
    "\$router->get('/caddy/ask'",
] as $needle) {
    if (!str_contains($indexText, $needle)) {
        fwrite(STDERR, "public/index.php missing expected Caddy ask fragment: {$needle}\n");
        exit(1);
    }
}

echo "Caddy ask smoke test passed.\n";

// End of file.
