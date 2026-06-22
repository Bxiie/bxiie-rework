<?php

declare(strict_types=1);

use App\Platform\Directory\TenantDirectoryProfileRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repository = new TenantDirectoryProfileRepository(Database::connect($root));
$count = $repository->rebuildAll();

echo json_encode(
    ['tenants_synced' => $count],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;

// End of file.
