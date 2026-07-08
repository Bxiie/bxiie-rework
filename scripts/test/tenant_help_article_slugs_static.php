<?php

declare(strict_types=1);

/**
 * Regression check for the platform help sidebar and article slug handling.
 */

$root = dirname(__DIR__, 2);
$path = $root . '/app/Http/Controllers/Platform/HelpController.php';
$source = file_get_contents($path);

$failures = [];

$requiredMarkers = [
    "'new-admin-tour' => ['/help/new-admin-tour', 'New admin setup tour']",
    "'tenant-admin-functions' => ['/help/tenant-admin-functions', 'Tenant function index']",
    "'training-videos' => ['/help/training-videos', 'Training videos']",
    '$slug = $this->normalizeArticleSlug((string) $slug);',
    "'new-admin-setup-tour' => 'new-admin-tour'",
    "'tenant-function-index' => 'tenant-admin-functions'",
];

foreach ($requiredMarkers as $marker) {
    if (strpos($source, $marker) === false) {
        $failures[] = 'Missing marker: ' . $marker;
    }
}

$badMarkers = [
    "['New admin setup tour', '/help/new-admin-tour']",
    "['Tenant function index', '/help/tenant-admin-functions']",
    "['Training videos', '/help/training-videos']",
];

foreach ($badMarkers as $marker) {
    if (strpos($source, $marker) !== false) {
        $failures[] = 'Broken label-first sidebar marker still present: ' . $marker;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Help article slug static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '[FAIL]  - ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Help article slug static check passed.\n");

// End of file.
