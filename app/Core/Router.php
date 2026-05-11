<?php
/**
 * Request router for public and admin routes.
 */

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\PublicController;
use App\Services\TenantResolver;

final class Router
{
    public function __construct(private array $container)
    {
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $tenant = (new TenantResolver($this->container['db'], $this->container['config']))->resolve($_SERVER['HTTP_HOST'] ?? '', $path);

        if (str_starts_with($path, '/admin')) {
            $this->routeAdmin($method, $path, $tenant);
            return;
        }

        $controller = new PublicController($this->container, $tenant);
        match (true) {
            $path === '/' || preg_match('#^/artist/[^/]+/?$#', $path) => $controller->home(),
            $path === '/portfolio' || preg_match('#^/portfolio/[^/]+/?$#', $path) => $controller->portfolio($path),
            $path === '/about' => $controller->about(),
            $path === '/contact' => $controller->contact($method),
            $path === '/subscribe' => $controller->subscribe($method),
            str_starts_with($path, '/image/') => $controller->image($path),
            str_starts_with($path, '/media/') => $controller->media($path),
            default => $controller->notFound(),
        };
    }

    private function routeAdmin(string $method, string $path, array $tenant): void
    {
        $auth = new AuthController($this->container, $tenant);
        if ($path === '/admin/login') {
            $auth->login($method);
            return;
        }
        if ($path === '/admin/logout') {
            $auth->logout();
            return;
        }
        $auth->requireLogin();

        $controller = new AdminController($this->container, $tenant);
        match (true) {
            $path === '/admin' => $controller->dashboard(),
            $path === '/admin/site' => $controller->site($method),
            $path === '/admin/images' => $controller->images($method),
            $path === '/admin/images/edit' => $controller->imageEdit($method),
            $path === '/admin/portfolio' => $controller->portfolio($method),
            $path === '/admin/events' => $controller->events($method),
            $path === '/admin/events/edit' => $controller->eventEdit($method),
            $path === '/admin/content' => $controller->content($method),
            $path === '/admin/messages' => $controller->messages(),
            $path === '/admin/users' => $controller->users($method),
            $path === '/admin/stats' => $controller->stats(),
            default => $controller->notFound(),
        };
    }
}

// End of file.
