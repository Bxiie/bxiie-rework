<?php

declare(strict_types=1);

/**
 * Manual verification script for platform custom domain list repository.
 */

use App\Platform\Domains\DomainAdminRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$domains = (new DomainAdminRepository(Database::connect($root)))->latest(10);

echo json_encode([
    'count' => count($domains),
    'domains' => $domains,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
