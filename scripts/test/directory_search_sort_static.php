<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Platform/Directory/TenantDirectoryProfileRepository.php' => [
        "'name_desc' => 'sort_name DESC, tenant_id DESC'",
        "'updated_desc' => 'updated_at DESC, sort_name ASC, tenant_id ASC'",
        'display_name LIKE :query',
        'summary LIKE :query',
        'private static function escapeLike',
    ],
    'app/Http/Controllers/Platform/DirectoryController.php' => [
        "private const SORTS = ['name_asc', 'name_desc', 'updated_desc']",
        'Search artists',
        'Name A–Z',
        'Name Z–A',
        'Recently updated',
        'data-directory-pager-root',
        'data-directory-page-form',
        'data-directory-page-link',
        '/assets/directory-pagination.js',
    ],
    'public/assets/directory-pagination.js' => [
        "const rootSelector = '[data-directory-pager-root]'",
        'window.history.pushState',
        "window.addEventListener('popstate'",
        "'X-ArtsFolio-Partial': 'directory-list'",
        'data-directory-sort',
    ],
];

foreach ($checks as $relative => $needles) {
    $text = file_get_contents($root . '/' . $relative);
    if ($text === false) {
        throw new RuntimeException("Missing {$relative}");
    }
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            throw new RuntimeException("Missing {$needle} in {$relative}");
        }
    }
}

$controller = (string) file_get_contents($root . '/app/Http/Controllers/Platform/DirectoryController.php');
if (str_contains($controller, 'LIMIT 100')) {
    throw new RuntimeException('Legacy directory hard limit returned.');
}


echo "Directory search and sort static checks passed.\n";

// End of file.
