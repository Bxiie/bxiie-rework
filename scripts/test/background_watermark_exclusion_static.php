<?php

declare(strict_types=1);

/** Regression checks for background-image watermark exclusion. */

$root = dirname(__DIR__, 2);
$media = file_get_contents($root . '/app/Http/Controllers/Tenant/MediaController.php');
$home = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php');

if ($media === false || $home === false) {
    fwrite(STDERR, "[FAIL] Could not read background watermark source files.
");
    exit(1);
}

$needles = [
    "\$_GET['usage']",
    "=== 'background'",
    '&& !$isBackgroundRequest',
    "\$variantKey !== 'thumb'",
    '$isBackgroundRequest = true;',
];

foreach ($needles as $needle) {
    if (!str_contains($media, $needle)) {
        fwrite(STDERR, "[FAIL] MediaController.php missing: {$needle}
");
        exit(1);
    }
}

if (!str_contains($home, '&usage=background')) {
    fwrite(STDERR, "[FAIL] HomeController.php does not mark background URLs.
");
    exit(1);
}

echo "[PASS] Background watermark exclusion checks passed.
";

// End of file.
