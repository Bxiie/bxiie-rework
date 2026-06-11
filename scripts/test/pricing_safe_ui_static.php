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

if (
    !str_contains($cssContent, 'rgba(255, 255, 255, 0.88)')
    && !str_contains($cssContent, 'rgba(255, 255, 255, 0.86)')
    && !str_contains($cssContent, 'rgba(255,255,255,.88)')
    && !str_contains($cssContent, 'rgba(255,255,255,.86)')
) {
    fwrite(STDERR, "FAILED: pricing/editor CSS missing readable white feature text color.\n");
    exit(1);
}

foreach ([
    'Pricing card legibility: dark/featured cards',
    
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
