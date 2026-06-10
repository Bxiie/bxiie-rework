<?php

declare(strict_types=1);

/**
 * Static regression test for the shared ArtsFolio email branding shell.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

use App\Platform\Email\BrandedEmail;

$text = BrandedEmail::text('Test message', 'Body content.');
$html = BrandedEmail::htmlFromText('Test message', 'Body content.');

$requiredText = [
    'ArtsFolio',
    'Artist portfolios, sites, and audience tools.',
    'Test message',
    'Body content.',
    'Sent by ArtsFolio.',
    'Terracopia, LLC',
];

foreach ($requiredText as $needle) {
    if (!str_contains($text, $needle)) {
        fwrite(STDERR, "Missing text branding fragment: {$needle}\n");
        exit(1);
    }
}

$requiredHtml = [
    '<!doctype html>',
    'ArtsFolio',
    'Artist portfolios, sites, and audience tools.',
    'Test message',
    'Body content.',
    'Sent by ArtsFolio.',
    'Terracopia, LLC',
];

foreach ($requiredHtml as $needle) {
    if (!str_contains($html, $needle)) {
        fwrite(STDERR, "Missing HTML branding fragment: {$needle}\n");
        exit(1);
    }
}

echo "Email branding static test passed.\n";

// End of file.
