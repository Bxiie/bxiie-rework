<?php
$root = dirname(__DIR__, 2);
$css = $root . '/public/assets/tenant-admin.css';
$js = $root . '/public/assets/tenant-admin-layout-rescue.js';
$errors = [];

if (!is_file($css)) {
    $errors[] = 'tenant-admin.css missing at public/assets/tenant-admin.css';
} else {
    $cssContents = file_get_contents($css);
    foreach ([
        'artsfolio-content-colors-layout-v13 start',
        '.js-af-image-picker-row',
        '.js-af-picker-thumb',
        '.js-af-picker-title',
        '.js-af-picker-action',
        '.js-af-broken-image',
        '.js-af-extra-color-swatch',
        'input[type="color"]',
    ] as $needle) {
        if (strpos($cssContents, $needle) === false) {
            $errors[] = "tenant-admin.css missing layout marker: $needle";
        }
    }
}

if (!is_file($js)) {
    $errors[] = 'tenant-admin-layout-rescue.js missing';
} else {
    $jsContents = file_get_contents($js);
    foreach ([
        'Static-test marker: Image unavailable',
        'normalizePickerRow',
        'buttonLooksLikeChangeImage',
        'js-af-picker-placeholder',
        'normalizeColorRows',
        'js-af-extra-color-swatch',
    ] as $needle) {
        if (strpos($jsContents, $needle) === false) {
            $errors[] = "layout rescue JS missing marker: $needle";
        }
    }
}

$liveRoots = ['app', 'resources', 'views', 'templates'];
$foundScript = false;
$badStaticCssPaths = [];
foreach ($liveRoots as $dir) {
    $base = $root . '/' . $dir;
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') continue;
        $contents = file_get_contents($file->getPathname());
        if (strpos($contents, 'tenant-admin-layout-rescue.js') !== false) $foundScript = true;
    }
}
$testBase = $root . '/scripts/test';
if (is_dir($testBase)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testBase, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') continue;
        $contents = file_get_contents($file->getPathname());
        if (preg_match('/file_get_contents\([^)]*tenant-admin\.css\?v=/', $contents)) {
            $badStaticCssPaths[] = str_replace($root . '/', '', $file->getPathname());
        }
    }
}
if (!$foundScript) {
    $errors[] = 'layout rescue JS is not referenced by any live app/template PHP file';
}
if ($badStaticCssPaths) {
    $errors[] = 'Static tests still use cache-busted tenant-admin.css as a filesystem path: ' . implode(', ', $badStaticCssPaths);
}

if ($errors) {
    fwrite(STDERR, "Content/colors background controls layout static check failed:
 - " . implode("
 - ", $errors) . "
");
    exit(1);
}

echo "Content/colors background controls layout static checks passed.
";
