<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$asset = $root . '/public/assets/artsfol-wordmark.png';
if (!is_file($asset)) {
    fwrite(STDERR, "[FAIL] Transparent ArtsFol.io wordmark asset is missing.\n");
    exit(1);
}
$png = file_get_contents($asset);
if ($png === false || strlen($png) < 33 || substr($png, 1, 3) !== 'PNG') {
    fwrite(STDERR, "[FAIL] ArtsFol.io wordmark is not a readable PNG.\n");
    exit(1);
}
// PNG color type byte in IHDR. Type 4 or 6 includes alpha.
$colorType = ord($png[25]);
if (!in_array($colorType, [4, 6], true)) {
    fwrite(STDERR, "[FAIL] ArtsFol.io wordmark PNG does not contain an alpha channel.\n");
    exit(1);
}

$legacy = ['logo_2.png', 'logo_2_medium.png', 'logo_2_1024.png', '/assets/logo.png'];
$scanRoots = [
    $root . '/app/Platform/Email',
    $root . '/template/email',
    $root . '/app/Platform/Monitoring/HealthReport.php',
];
$failures = [];
foreach ($scanRoots as $scanRoot) {
    $files = [];
    if (is_file($scanRoot)) {
        $files[] = $scanRoot;
    } elseif (is_dir($scanRoot)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && !str_starts_with($file->getFilename(), '._')) {
                $files[] = $file->getPathname();
            }
        }
    }
    foreach ($files as $file) {
        $source = file_get_contents($file);
        if ($source === false) {
            continue;
        }
        foreach ($legacy as $needle) {
            if (str_contains($source, $needle)) {
                $failures[] = str_replace($root . '/', '', $file) . ': ' . $needle;
            }
        }
    }
}

$branded = $root . '/app/Platform/Email/BrandedEmail.php';
$source = is_file($branded) ? file_get_contents($branded) : false;
if ($source === false || !str_contains($source, '/assets/artsfol-wordmark.png')) {
    $failures[] = 'BrandedEmail.php does not use /assets/artsfol-wordmark.png';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Legacy email logo references remain:\n[FAIL]  - " . implode("\n[FAIL]  - ", $failures) . "\n");
    exit(1);
}

echo "[PASS] Every email branding path uses the transparent ArtsFol.io wordmark.\n";
