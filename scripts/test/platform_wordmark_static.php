<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$asset = $root . '/public/assets/artsfol-wordmark.png';
$failures = [];

if (!is_file($asset)) {
    $failures[] = 'Missing public/assets/artsfol-wordmark.png.';
} else {
    $info = @getimagesize($asset);
    if (!is_array($info) || ($info[2] ?? null) !== IMAGETYPE_PNG) {
        $failures[] = 'Wordmark asset is not a valid PNG.';
    }
    $contents = @file_get_contents($asset);
    if (!is_string($contents) || strlen($contents) < 25) {
        $failures[] = 'Wordmark asset is unreadable or unexpectedly small.';
    } elseif (ord($contents[25]) !== 6) {
        $failures[] = 'Wordmark PNG must use RGBA color type 6 with real transparency.';
    }
}

$runtimeRoots = [$root . '/app', $root . '/public'];
$legacyPatterns = ['/assets/logo_2.png', '/assets/logo_2_medium.png', '/assets/logo_2_1024.png', '/assets/logo.png'];
$newReferenceFiles = [];
foreach ($runtimeRoots as $runtimeRoot) {
    if (!is_dir($runtimeRoot)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtimeRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || str_starts_with($file->getFilename(), '._')) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'css', 'html', 'htm', 'js'], true)) {
            continue;
        }
        $data = @file_get_contents($file->getPathname());
        if (!is_string($data)) {
            continue;
        }
        foreach ($legacyPatterns as $legacy) {
            if (str_contains($data, $legacy)) {
                $failures[] = str_replace($root . '/', '', $file->getPathname()) . ' still references ' . $legacy . '.';
            }
        }
        if (str_contains($data, '/assets/artsfol-wordmark.png')) {
            $newReferenceFiles[] = str_replace($root . '/', '', $file->getPathname());
        }
    }
}

$required = [
    'app/Http/View/AdminLayout.php',
    'app/Http/View/AuthPage.php',
    'app/Http/View/ErrorPage.php',
    'app/Platform/Email/BrandedEmail.php',
    'app/Platform/Monitoring/HealthReport.php',
];
foreach ($required as $relative) {
    $path = $root . '/' . $relative;
    $data = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($data) || !str_contains($data, '/assets/artsfol-wordmark.png')) {
        $failures[] = $relative . ' does not reference the new wordmark.';
    }
}

if (count(array_unique($newReferenceFiles)) < 10) {
    $failures[] = 'Too few platform UI/email source files reference the new wordmark; expected at least 10, found ' . count(array_unique($newReferenceFiles)) . '.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Platform wordmark static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '[FAIL]  - ' . $failure . "\n");
    }
    exit(1);
}

echo "[PASS] Platform wordmark static check passed.\n";
