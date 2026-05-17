<?php

declare(strict_types=1);

/**
 * Manual verification script for pagination helper.
 */

use App\Support\Pagination\Pagination;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$result = [
    'page' => Pagination::pageFromQuery('3'),
    'limit' => Pagination::limitFromQuery('999', 50, 200),
    'offset' => Pagination::offset(3, 50),
    'next' => Pagination::nextPageUrl('/admin/audit-log', ['action' => 'x'], 3),
    'previous' => Pagination::previousPageUrl('/admin/audit-log', ['action' => 'x'], 3),
];

if ($result['offset'] !== 100 || $result['limit'] !== 200) {
    fwrite(STDERR, "Unexpected pagination result.\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
