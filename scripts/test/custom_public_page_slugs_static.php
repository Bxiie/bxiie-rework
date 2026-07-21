<?php

/**
 * Static regression coverage for tenant-configurable public page slugs.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routesPath = $root . '/app/Http/Routes/tenant.php';
$routes = file_get_contents($routesPath);

if ($routes === false) {
    fwrite(STDERR, "[FAIL] Unable to read {$routesPath}.\n");
    exit(1);
}

$requiredMarkers = [
    "\$router->get('/portfolio', \$portfolioHandler);",
    "\$portfolioPath = '/' . \$portfolioSlug;",
    "\$router->get(\$portfolioPath, \$portfolioHandler);",
    "\$router->get('/about', \$aboutHandler);",
    "\$aboutPath = '/' . \$aboutSlug;",
    "\$router->get(\$aboutPath, \$aboutHandler);",
    "\$router->get('/contact', \$contactPageHandler);",
    "\$router->post('/contact', \$contactSubmitHandler);",
    "\$contactPath = '/' . \$contactSlug;",
    "\$router->get(\$contactPath, \$contactPageHandler);",
    "\$router->post(\$contactPath, \$contactSubmitHandler);",
];

$errors = [];
foreach ($requiredMarkers as $marker) {
    if (!str_contains($routes, $marker)) {
        $errors[] = "Tenant routes missing marker: {$marker}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Custom public page slug regression check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Custom public page slug regression check passed.\n");

// End of file.
