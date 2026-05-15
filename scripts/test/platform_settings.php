<?php

declare(strict_types=1);

/**
 * Manual verification script for platform settings read/write behavior.
 */

use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$settings = new PlatformSettingsRepository($pdo);

$settings->set('platform_name', 'Arts Folio');
$settings->set('marketing_domain', 'artsfol.io');
$settings->set('admin_domain', 'app.artsfol.io');

echo json_encode($settings->all(), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
