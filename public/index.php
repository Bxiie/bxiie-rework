<?php

declare(strict_types=1);

use App\Http\AppKernel;
use App\Http\Request;
use App\Http\View\ErrorPage;

/** Main HTTP front controller. */

// Canonicalize platform-admin traffic before tenant resolution.
$earlyHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$earlyHost = preg_replace('/:\d+$/', '', $earlyHost) ?? $earlyHost;
$earlyUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$earlyPath = parse_url($earlyUri, PHP_URL_PATH) ?: '/';

if ($earlyHost !== 'artsfol.io' && str_starts_with($earlyPath, '/platform/admin')) {
    header('Location: https://artsfol.io' . $earlyUri, true, 302);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/app.php';

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    if (!in_array((int) $error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    ErrorPage::sendFatal($error);
});

session_start();
(new AppKernel($root))->run(Request::fromGlobals());

// End of file.
