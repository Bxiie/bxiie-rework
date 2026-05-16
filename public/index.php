<?php

declare(strict_types=1);

/**
 * Main HTTP front controller for local and production web requests.
 */

use App\Http\Controllers\Platform\HomeController as PlatformHomeController;
use App\Http\Controllers\Tenant\HomeController as TenantHomeController;
use App\Http\Middleware\ResolveTenant;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Settings\TenantSettingsRepository;

$root = dirname(__DIR__);

require $root . '/bootstrap/app.php';

$request = Request::fromGlobals();

try {
    $pdo = Database::connect($root);
    $tenantResolver = new TenantResolver($pdo);
    $tenant = (new ResolveTenant($tenantResolver))->handle($request);

    if ($tenant) {
        $tenantController = new TenantHomeController(new TenantSettingsRepository($pdo));

        $router = new Router();
        $router->get('/', fn (Request $request): Response => $tenantController->home($request, $tenant));
        $router->get('/portfolio', fn (Request $request): Response => $tenantController->portfolio($request, $tenant));
        $router->get('/about', fn (Request $request): Response => $tenantController->about($request, $tenant));
        $router->get('/contact', fn (Request $request): Response => $tenantController->contact($request, $tenant));

        $router->dispatch($request)->send();
        exit;
    }

    $platformController = new PlatformHomeController();

    $router = new Router();
    $router->get('/', fn (Request $request): Response => $platformController->home($request));
    $router->get('/pricing', fn (Request $request): Response => $platformController->pricing($request));
    $router->get('/signup', fn (Request $request): Response => $platformController->signup($request));
    $router->get('/login', fn (Request $request): Response => $platformController->login($request));

    $router->dispatch($request)->send();
} catch (Throwable $e) {
    Response::html(
        "<h1>Application error</h1>\n<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>\n",
        500
    )->send();
}

// End of file.
