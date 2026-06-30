<?php
$root = dirname(__DIR__, 2);
$errors = [];

$cssCandidates = [
    $root . '/public/assets/tenant-admin.css',
    $root . '/public/assets/css/tenant-admin.css',
    $root . '/public/css/tenant-admin.css',
];
$cssPath = null;
foreach ($cssCandidates as $candidate) {
    if (is_file($candidate)) {
        $cssPath = $candidate;
        break;
    }
}
if (!$cssPath) {
    $errors[] = 'tenant-admin.css not found in expected public locations.';
} else {
    $css = file_get_contents($cssPath);
    foreach ([
        'content-colors-bg-image-picker-layout-20260630',
        '[class*="image-picker"]',
        '[class*="selected-image"]',
        '[class*="media-picker"]',
        'overflow-wrap: anywhere',
        'flex-wrap: wrap',
        'grid-template-columns: 1fr !important',
        'max-width: min(10rem, 42vw)',
    ] as $needle) {
        if (strpos($css, $needle) === false) {
            $errors[] = "Missing layout repair CSS marker/value: {$needle}";
        }
    }
}

$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$foundCacheBust = false;
$oldUnbusted = [];
foreach ($phpFiles as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (!preg_match('/\.(php|phtml|html)$/', $path)) {
        continue;
    }
    if (strpos($path, '/vendor/') !== false || strpos($path, '/.update-backups/') !== false || strpos($path, '/scripts/test/') !== false) {
        continue;
    }
    $text = file_get_contents($path);
    if (strpos($text, 'tenant-admin.css') !== false) {
        $foundCacheBust = true;
    }
    if (preg_match('/tenant-admin\.css(?!\?v=20260630-content-colors-bg-image-picker-layout)/', $text)) {
        $oldUnbusted[] = str_replace($root . '/', '', $path);
    }
}
if (!$foundCacheBust) {
    $errors[] = 'No template references the refreshed tenant-admin.css cache-bust value.';
}
if ($oldUnbusted) {
    $errors[] = 'Found tenant-admin.css references not using the refreshed cache-bust value: ' . implode(', ', array_slice($oldUnbusted, 0, 8));
}

if ($errors) {
    fwrite(STDERR, "[FAIL] Content/colors background image picker layout static check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }
    exit(1);
}

echo "Content/colors background image picker layout static checks passed.\n";
