<?php

declare(strict_types=1);

/**
 * Verifies that public help copy speaks directly to the artist instead of
 * using stiff third-person tenant language.
 */

$root = dirname(__DIR__, 2);
$controller = $root . '/app/Http/Controllers/Platform/HelpController.php';
$doc = $root . '/docs/user/tenant-admin-help.md';

$errors = [];
foreach ([$controller, $doc] as $file) {
    if (!is_file($file)) {
        $errors[] = "Missing file: {$file}";
    }
}

if (!$errors) {
    $controllerText = file_get_contents($controller) ?: '';
    $docText = file_get_contents($doc) ?: '';

    $required = [
        'your site',
        'your branding',
        'your art',
        'Your admin tools',
        'your events',
        'your stats',
        'your email list',
        'artist-facing guide',
    ];

    foreach ($required as $needle) {
        if (!str_contains($controllerText . $docText, $needle)) {
            $errors[] = "Missing friendly voice marker: {$needle}";
        }
    }

    $forbidden = [
        'the tenant admin',
        'a tenant admin',
        'new tenant admin',
        'Tenant admins',
        'tenant admins',
        'the tenant ',
        'The tenant ',
        'tenant-local',
        'Tenant function index',
    ];

    foreach ($forbidden as $needle) {
        if (str_contains($controllerText, $needle)) {
            $errors[] = "Found third-person help wording in HelpController: {$needle}";
        }
    }
}

if ($errors) {
    fwrite(STDERR, "[FAIL] Tenant help artist-voice static check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }
    exit(1);
}

echo "[PASS] Tenant help artist-voice static check passed.\n";

// End of file.
