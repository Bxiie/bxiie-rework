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
$required = [
    'v8 layout marker' => 'content-colors-bg-controls-layout-20260630-v8',
    'selected-image broad selector' => '[class*="selected-image"]:has(button)',
    'image-picker broad selector' => '[class*="image-picker"]:has(img):has(button)',
    'selected-image grid lanes' => 'grid-template-columns: minmax(9rem, max-content) minmax(8rem, 10.5rem) minmax(0, 1fr) max-content',
    'children shrink guard' => 'min-width: 0 !important',
    'duplicate unavailable image hidden' => 'img[alt="Image unavailable"]',
    'image title wraps' => 'overflow-wrap: anywhere !important',
    'change button nowrap' => 'white-space: nowrap !important',
    'color input large swatch' => 'input[type="color"]',
    'color layout two columns' => 'grid-template-columns: minmax(9rem, 1fr) minmax(14rem, 1fr)',
    'small color swatches hidden' => '[class*="color-swatch"]',
    'trailing color preview hidden' => ':has(input[type="color"]) > :last-child:not(input[type="color"])',
];
foreach ($required as $label => $needle) {
    if (strpos($css, $needle) === false) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}
// Only live tests in scripts/test should be checked. Do not inspect storage/update-backups.
$testDir = $root . '/scripts/test';
foreach (glob($testDir . '/*.php') ?: [] as $file) {
    $body = file_get_contents($file);
    if (preg_match('/file_get_contents\([^\)]*tenant-admin\.css\?v=/', $body)) {
        $failures[] = 'Static test uses cache-busted tenant-admin.css as a filesystem path: ' . basename($file);
    }
}
if ($failures) {
    fwrite(STDERR, "Content/colors background controls layout static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - " . $failure . "\n");
    }
    exit(1);
}
echo "Content/colors background controls layout static checks passed.\n";
