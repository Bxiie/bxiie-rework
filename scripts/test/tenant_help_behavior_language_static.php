<?php

declare(strict_types=1);

$controllerPath = __DIR__ . '/../../app/Http/Controllers/Platform/HelpController.php';
$source = file_get_contents($controllerPath);
if ($source === false) {
    fwrite(STDERR, "[FAIL] Could not read HelpController.php\n");
    exit(1);
}

$marker = "\n    public function index";
$parts = explode($marker, $source, 2);
if (count($parts) !== 2) {
    fwrite(STDERR, "[FAIL] Could not isolate artist-facing help articles.\n");
    exit(1);
}

$articles = $parts[0];
$failures = [];

$forbidden = [
    '<code>/admin',
    'Open /admin',
    'Go to /admin',
    'Open `/admin',
    'Go to `/admin',
    'Open <code>/admin',
    'Go to <code>/admin',
];

foreach ($forbidden as $needle) {
    if (str_contains($articles, $needle)) {
        $failures[] = "Artist-facing help still contains path-style instruction marker: {$needle}";
    }
}

$required = [
    "Click <strong>Settings</strong> in the sidebar",
    "Click <strong>Content</strong> in the sidebar",
    "Click <strong>Upload Artwork</strong> in the sidebar",
    "Click <strong>Events</strong> in the sidebar",
    "Click <strong>Stats</strong> in the sidebar",
];

foreach ($required as $needle) {
    if (!str_contains($articles, $needle)) {
        $failures[] = "Missing behavior-first help instruction: {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant help behavior-language static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Tenant help behavior-language static check passed.\n");

// End of file.
