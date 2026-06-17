<?php

declare(strict_types=1);

/**
 * Static regression checks for ArtsFolio public Terms and Privacy pages.
 */

$root = dirname(__DIR__, 2);
if (!is_file($root . '/index.php') && is_file(dirname(__DIR__) . '/index.php')) {
    $root = dirname(__DIR__);
}

$marketingCandidates = [
    $root . '/app/Http/Controllers/Platform/MarketingController.php',
    $root . '/Http/Controllers/Platform/MarketingController.php',
];
$indexCandidates = [
    $root . '/public/index.php',
    $root . '/index.php',
];

$marketingPath = null;
foreach ($marketingCandidates as $candidate) {
    if (is_file($candidate)) {
        $marketingPath = $candidate;
        break;
    }
}

$indexPath = null;
foreach ($indexCandidates as $candidate) {
    if (is_file($candidate)) {
        $indexPath = $candidate;
        break;
    }
}

if ($marketingPath === null || $indexPath === null) {
    fwrite(STDERR, "Could not locate MarketingController.php or index.php\n");
    exit(1);
}

$marketing = file_get_contents($marketingPath);
$index = file_get_contents($indexPath);

if ($marketing === false || $index === false) {
    fwrite(STDERR, "Could not read platform legal page files\n");
    exit(1);
}

$checks = [
    'terms method' => 'public function terms(Request $request): Response',
    'terms route' => "'/terms'",
    'terms footer link' => 'href="/terms"',
    'public image license' => 'public artwork images on ArtsFolio home pages',
    'directory opt in' => 'opts into ArtsFolio directory or discovery features',
    'sales terms' => 'the artist or tenant is the seller of record',
    'signup codes terms' => 'free-access codes may expire',
    'privacy deletion instructions' => 'Data deletion request',
    'facebook deletion instructions' => 'Facebook data deletion request',
    'google deletion instructions' => 'Google data deletion request',
    'oauth privacy language' => 'OAuth identifiers',
];

$haystack = $marketing . "\n" . $index;
foreach ($checks as $label => $needle) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing platform legal static check: {$label}\n");
        exit(1);
    }
}

echo "Platform terms/privacy static checks passed.\n";

// End of file.
