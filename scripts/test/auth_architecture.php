<?php

declare(strict_types=1);

/**
 * Prints current authentication architecture constants for manual verification.
 */

use App\Platform\Auth\AuthArchitecture;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

echo json_encode([
    'ui_auth_models' => AuthArchitecture::UI_AUTH_MODELS,
    'api_auth_model' => AuthArchitecture::API_AUTH_MODEL,
    'supported_external_providers' => AuthArchitecture::SUPPORTED_EXTERNAL_PROVIDERS,
    'supported_local_providers' => AuthArchitecture::SUPPORTED_LOCAL_PROVIDERS,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
