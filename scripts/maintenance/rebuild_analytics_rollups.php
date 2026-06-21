<?php

declare(strict_types=1);

use App\Platform\Analytics\AnalyticsRollupService;
use App\Support\Database;

$root=dirname(__DIR__,2);
require $root.'/bootstrap/app.php';
$days=max(1,(int)($argv[1] ?? 30));
$result=(new AnalyticsRollupService(Database::connect($root)))->rebuildRecent($days);
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;

// End of file.
