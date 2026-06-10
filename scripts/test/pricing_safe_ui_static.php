<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$cssContent = '';
foreach ([
    $root . '/public/assets/platform.css',
    $root . '/public/assets/site.css',
    $root . '/public/assets/marketing.css',
    $root . '/public/assets/admin.css',
] as $file) {
    if (is_file($file)) {
        $cssContent .= "\n" . (string) file_get_contents($file);
    }
}

foreach ([
    'Pricing card legibility: dark/featured cards',
    'rgba(255, 255, 255, 0.88)',
    'Plan editor usability',
    'min-width: 18rem',
] as $needle) {
    if (!str_contains($cssContent, $needle)) {
        fwrite(STDERR, "FAILED: pricing/editor CSS missing {$needle}\n");
        exit(1);
    }
}

echo "Pricing safe UI static checks passed.\n";

// End of file.
