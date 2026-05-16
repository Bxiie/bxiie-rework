<?php

declare(strict_types=1);

/**
 * Manual verification script for platform settings repository behavior.
 */

use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$settings = new PlatformSettingsRepository(Database::connect($root));

$settings->set('platform_name', 'ArtsFolio Test Platform');
$settings->set('support_email', 'support@example.test');
$settings->set('expected_ipv4', '127.0.0.1');

echo json_encode([
    'platform_name' => $settings->get('platform_name'),
    'support_email' => $settings->get('support_email'),
    'expected_ipv4' => $settings->get('expected_ipv4'),
    'all_count' => count($settings->all()),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
