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
        $css .= "
" . (string) file_get_contents($file);
    }
}

foreach ([
    'Studio pricing card hard contrast override',
    'body .pricing-grid > article:nth-child(2) li',
    'color: rgba(255, 255, 255, 0.92) !important',
    'opacity: 1 !important',
] as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "FAILED: Studio pricing contrast CSS missing {$needle}
");
        exit(1);
    }
}

echo "Studio pricing contrast static checks passed.
";

// End of file.
