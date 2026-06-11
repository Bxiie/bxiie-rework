<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$css = '';
foreach ([
    $root . '/public/assets/platform.css',
    $root . '/public/assets/site.css',
    $root . '/public/assets/marketing.css',
    $root . '/public/assets/admin.css',
] as $file) {
    if (is_file($file)) {
        $css .= "\n" . (string) file_get_contents($file);
    }
}

foreach ([
    'Pricing UI repair: keep featured/Studio pricing card text visible',
    'color: rgba(255, 255, 255, 0.92) !important',
    'Plan editor usability',
    'min-width: 18rem',
] as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "FAILED: pricing/editor CSS missing {$needle}\n");
        exit(1);
    }
}

$js = '';
foreach ([
    $root . '/public/assets/platform.js',
    $root . '/public/assets/site.js',
    $root . '/public/assets/admin.js',
] as $file) {
    if (is_file($file)) {
        $js .= "\n" . (string) file_get_contents($file);
    }
}

foreach ([
    'Pricing UI repair: add admin-user details on pricing pages',
    '3 admin users',
    '<th>Admin users</th>',
] as $needle) {
    if (!str_contains($js, $needle)) {
        fwrite(STDERR, "FAILED: pricing admin-user enhancement missing {$needle}\n");
        exit(1);
    }
}

echo "Pricing DOM-safe UI static checks passed.\n";

// End of file.
