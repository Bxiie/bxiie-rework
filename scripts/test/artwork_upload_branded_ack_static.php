<?php

declare(strict_types=1);

/**
 * Static coverage for branded artwork upload acknowledgement.
 */

$root = dirname(__DIR__, 2);
$path = $root . '/app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php';

if (!is_file($path)) {
    fwrite(STDERR, "Missing ArtworkUploadController.php\n");
    exit(1);
}

$source = file_get_contents($path);

$required = [
    'private function uploadNoticeHtml(): string',
    '$uploadNotice = $this->uploadNoticeHtml();',
    '{$uploadNotice}',
    "'uploaded' => '1'",
    "'title' => (string) (\$record['title'] ?? '')",
    "'status' => (string) (\$record['status'] ?? 'draft')",
    "new Response('', 303, ['Location' => '/admin/artwork/upload?' . http_build_query(\$query)])",
    'admin-notice admin-notice-success',
    'Artwork uploaded.',
    'has been uploaded and saved as an unpublished draft.',
    'The form is ready for the next image.',
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing artwork upload acknowledgement marker: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'return Response::html("<h1>Artwork uploaded</h1>',
    "return Response::html('<h1>Artwork uploaded</h1>",
    '<h1>Artwork uploaded</h1><p>{$title}',
    'Review artworks',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "Forbidden raw/unbranded upload acknowledgement remains: {$needle}\n");
        exit(1);
    }
}

echo "Branded artwork upload acknowledgement static checks passed.\n";

// End of file.
