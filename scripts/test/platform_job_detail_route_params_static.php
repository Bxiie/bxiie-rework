<?php

declare(strict_types=1);

/**
 * Regression check for parameterized platform job detail routing.
 *
 * App\\Http\\Router dispatches route parameters as a single associative array.
 * The /platform/admin/jobs/{id} route must therefore accept array $params and
 * read $params['id']; accepting string $id causes a runtime TypeError.
 */

$root = dirname(__DIR__, 2);
$routeFile = $root . '/app/Http/Routes/platform.php';
$source = file_get_contents($routeFile);

if ($source === false) {
    fwrite(STDERR, "Could not read platform route file.\n");
    exit(1);
}

$required = [
    "\$router->get('/platform/admin/jobs/{id}', fn (Request \$request, array \$params): Response",
    "(\$params['id'] ?? 0)",
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing platform job detail route marker: {$needle}\n");
        exit(1);
    }
}

$forbidden = "\$router->get('/platform/admin/jobs/{id}', fn (Request \$request, string \$id): Response";
if (str_contains($source, $forbidden)) {
    fwrite(STDERR, "Platform job detail route still accepts string id instead of array params.\n");
    exit(1);
}

echo "Platform job detail route parameter static check passed.\n";

// End of file.
