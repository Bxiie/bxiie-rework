<?php

declare(strict_types=1);

/**
 * Static regression checks for branded CSRF failure responses.
 *
 * Browser-facing invalid-CSRF paths must render through Response::invalidCsrf()
 * instead of returning raw, unbranded HTML. This protects logout and admin POST
 * failures from leaking naked framework-style pages.
 */

$root = dirname(__DIR__, 2);
$errors = [];

$responsePath = $root . '/app/Http/Response.php';
$response = file_get_contents($responsePath) ?: '';
if (!str_contains($response, 'public static function invalidCsrf(')) {
    $errors[] = 'Response::invalidCsrf() helper is missing.';
}

$errorPagePath = $root . '/app/Http/View/ErrorPage.php';
$errorPage = file_get_contents($errorPagePath) ?: '';
if (!str_contains($errorPage, "419 => [") || !str_contains($errorPage, 'Security check expired')) {
    $errors[] = 'Branded ErrorPage 419 copy is missing.';
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
foreach ($rii as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php' || str_starts_with($file->getFilename(), '._')) {
        continue;
    }

    $path = $file->getPathname();
    $contents = file_get_contents($path) ?: '';
    if (str_contains($contents, "Response::html('<h1>Invalid CSRF token</h1>', 419)")) {
        $errors[] = str_replace($root . '/', '', $path) . ' still returns raw invalid-CSRF HTML.';
    }
}

$logoutPath = $root . '/app/Http/Controllers/Auth/PasswordAuthController.php';
$logoutController = file_get_contents($logoutPath) ?: '';
if (!str_contains($logoutController, 'return Response::invalidCsrf();')) {
    $errors[] = 'Platform logout invalid-CSRF path does not use Response::invalidCsrf().';
}

if ($errors !== []) {
    fwrite(STDERR, "Failed branded CSRF static check:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Branded CSRF static checks passed.\n";

// End of file.
