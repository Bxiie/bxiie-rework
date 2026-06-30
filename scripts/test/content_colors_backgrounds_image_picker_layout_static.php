<?php
$root = dirname(__DIR__, 2);
$cssPath = $root . '/public/assets/tenant-admin.css';
$failures = [];

if (!is_file($cssPath)) {
    $failures[] = 'tenant-admin.css not found at public/assets/tenant-admin.css';
    $css = '';
} else {
    $css = file_get_contents($cssPath);
}

$requiredCss = [
    'selected image grid repair marker' => 'content-colors-bg-controls-layout-20260630-v7',
    'selected image grid lanes' => 'grid-template-columns: minmax(8rem, max-content) minmax(7rem, 10rem) minmax(0, 1fr) max-content',
    'selected image children can shrink' => '.tenant-admin-panel .selected-image-row > *',
    'selected image titles wrap' => 'overflow-wrap: anywhere',
    'change image button stays on row end' => 'justify-self: end',
    'mobile selected image stack' => '@media (max-width: 760px)',
    'color picker layout grid' => '.tenant-admin-panel .tenant-color-control',
    'color picker is large swatch' => 'input[type="color"]',
    'small color swatch hidden' => 'display: none !important',
    'webkit color swatch styled' => '::-webkit-color-swatch',
    'mozilla color swatch styled' => '::-moz-color-swatch',
];

foreach ($requiredCss as $label => $needle) {
    if ($css !== '' && strpos($css, $needle) === false) {
        $failures[] = "Tenant admin CSS missing $label: $needle";
    }
}

$searchRoots = ['app', 'resources', 'views', 'templates'];
$staleRefs = [];
foreach ($searchRoots as $searchRoot) {
    $dir = $root . '/' . $searchRoot;
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (!preg_match('/\.(php|html|phtml)$/', $path) && !str_ends_with($path, '.blade.php')) {
            continue;
        }
        $body = file_get_contents($path);
        if (strpos($body, 'tenant-admin.css') === false) {
            continue;
        }
        if (strpos($body, 'tenant-admin.css?v=20260630-content-colors-bg-controls-layout-v7') === false) {
            $staleRefs[] = substr($path, strlen($root) + 1);
        }
    }
}

if ($staleRefs) {
    $failures[] = 'Found tenant-admin.css browser references not using v7 cache-bust: ' . implode(', ', $staleRefs);
}

$badFilesystemReads = [];
$testDir = $root . '/scripts/test';
if (is_dir($testDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || pathinfo($file->getFilename(), PATHINFO_EXTENSION) !== 'php') {
            continue;
        }
        $body = file_get_contents($file->getPathname());
        if (preg_match('/file_get_contents\([^)]*tenant-admin\.css\?v=/', $body)) {
            $badFilesystemReads[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
}

if ($badFilesystemReads) {
    $failures[] = 'Static tests still use cache-busted tenant-admin.css as a filesystem path: ' . implode(', ', $badFilesystemReads);
}

if ($failures) {
    fwrite(STDERR, "Content/colors background controls layout static check failed:
");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - $failure
");
    }
    exit(1);
}

echo "Content/colors background controls layout static checks passed.
";
