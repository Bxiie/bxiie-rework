<?php
$root = dirname(__DIR__, 2);
$cssCandidates = [
    $root . '/public/assets/tenant-admin.css',
    $root . '/public/assets/css/tenant-admin.css',
];
$cssPath = null;
foreach ($cssCandidates as $candidate) {
    if (is_file($candidate)) {
        $cssPath = $candidate;
        break;
    }
}
$failures = [];
if ($cssPath === null) {
    $failures[] = 'tenant-admin.css not found in expected public locations.';
    $css = '';
} else {
    $css = file_get_contents($cssPath);
}
$requiredCss = [
    'content-colors-backgrounds-controls-layout-20260630-v10:start',
    'img[alt*="image unavailable" i]',
    'grid-template-columns: minmax(9.5rem, max-content) 10.5rem minmax(14rem, 1fr) max-content',
    'input[type="color"]::-webkit-color-swatch',
    'Remove the redundant tiny trailing color swatches',
    'main input[type="color"] ~ [class*="swatch"]',
];
foreach ($requiredCss as $needle) {
    if ($css !== '' && strpos($css, $needle) === false) {
        $failures[] = 'tenant-admin.css missing layout marker: ' . $needle;
    }
}
$testDir = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testDir, FilesystemIterator::SKIP_DOTS));
$badTestPaths = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $contents = file_get_contents($path);
    if (preg_match('/file_get_contents\([^\)]*tenant-admin\.css\?v=/', $contents)) {
        $badTestPaths[] = substr($path, strlen($root) + 1);
    }
}
if ($badTestPaths) {
    $failures[] = 'Live static tests still use cache-busted tenant-admin.css as a filesystem path: ' . implode(', ', $badTestPaths);
}
if ($failures) {
    fwrite(STDERR, "Content/colors background controls layout static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}
echo "Content/colors background controls layout static checks passed.\n";
