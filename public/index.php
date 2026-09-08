<?php

declare(strict_types=1);

use App\Http\AppKernel;
use App\Http\Controllers\Tenant\SocialFrontController;
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
$request = Request::fromGlobals();

// Social publishing uses an isolated route surface so Meta's canonical OAuth
// callback and temporary media URLs can be handled before tenant route guards.
$socialResponse = (new SocialFrontController($root))->handle($request);
if ($socialResponse !== null) {
    $socialResponse->send();
    exit;
}

// Progressive social controls are injected into ordinary rendered pages. The
// script performs a tenant-scoped authorization check and is a no-op for users
// without social publishing permission or for unrelated platform pages.
ob_start(static function (string $html): string {
    if (!str_contains($html, '</body>') || str_contains($html, '/assets/social-publishing.js')) {
        return $html;
    }
    return str_replace('</body>', '<script src="/assets/social-publishing.js?v=20260908" defer></script></body>', $html);
});

(new AppKernel($root))->run($request);

// End of file.
