<?php

/**
 * Static checks for tenant login and background jobs UI behavior.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$loginFiles = [
    $root . '/app/Http/Controllers/Auth/LoginController.php',
    $root . '/template/auth/login.php',
    $root . '/template/tenant/login.php',
];

foreach ($loginFiles as $file) {
    if (!is_file($file)) {
        continue;
    }

    $content = strtolower((string) file_get_contents($file));
    if ((str_contains($content, 'create an account') || str_contains($content, 'sign up')) && str_contains($content, '/signup')) {
        fwrite(STDERR, "FAILED: tenant login still exposes create-account/signup link in {$file}\n");
        exit(1);
    }
}

$jobsOk = false;
$appRoot = $root . '/app';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    $basename = $fileInfo->getBasename();

    if (!str_ends_with($basename, '.php')) {
        continue;
    }

    if (!str_contains($basename, 'Jobs') && !str_contains($basename, 'Background')) {
        continue;
    }

    $content = (string) file_get_contents($path);
    if (str_contains($content, 'Execution time') && str_contains($content, 'formatJobExecutionTime')) {
        $jobsOk = true;
        break;
    }
}

if (!$jobsOk) {
    fwrite(STDERR, "FAILED: background jobs page does not expose execution date/time.\n");
    exit(1);
}

echo "Login and background jobs UI static checks passed.\n";

// End of file.
