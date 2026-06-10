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
use PDO;

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
        $slugs = $this->slugsForTenant((int) $tenant['id']);
        $portfolio = '/' . $slugs['portfolio'];
        $about = '/' . $slugs['about'];
        $contact = '/' . $slugs['contact'];

        match (true) {
            $path === '/' || preg_match('#^/artist/[^/]+/?$#', $path) => $controller->home(),
            $path === $portfolio || preg_match('#^' . preg_quote($portfolio, '#') . '/[^/]+/?$#', $path) => $controller->portfolio($path),
            $path === '/portfolio' || preg_match('#^/portfolio/[^/]+/?$#', $path) => $controller->portfolio($path),
            $path === $about || $path === '/about' => $controller->about(),
            $path === $contact || $path === '/contact' => $controller->contact($method),
            $path === '/subscribe' => $controller->subscribe($method),
            $path === '/tenant.css' => $controller->tenantCss(),
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
            $path === '/admin/portfolio/edit' => $controller->portfolioEdit($method),
            $path === '/admin/events' => $controller->events($method),
            $path === '/admin/events/edit' => $controller->eventEdit($method),
            $path === '/admin/content' => $controller->content($method),
            $path === '/admin/messages' => $controller->messages(),
            $path === '/admin/messages/delete' => $controller->deleteMessage($method),
            $path === '/admin/subscribers' => $controller->subscribers(),
            $path === '/admin/subscribers/export' => $controller->exportSubscribers(),
            $path === '/admin/users' => $controller->users($method),
            $path === '/admin/stats' => $controller->stats(),
            $path === '/admin/stats/reset' => $controller->resetStats($method),
            default => $controller->notFound(),
        };
    }

    private function slugsForTenant(int $tenantId): array
    {
        $db = $this->container['db'];
        if (!$db instanceof PDO) {
            return ['portfolio' => 'portfolio', 'about' => 'about', 'contact' => 'contact'];
        }

        $stmt = $db->prepare('SELECT setting_key, setting_value FROM settings WHERE tenant_id = :tenant_id AND setting_key IN ("portfolio_slug", "about_slug", "contact_slug")');
        $stmt->execute(['tenant_id' => $tenantId]);
        $settings = array_column($stmt->fetchAll(), 'setting_value', 'setting_key');

        return [
            'portfolio' => $this->safeSlug($settings['portfolio_slug'] ?? 'portfolio', 'portfolio'),
            'about' => $this->safeSlug($settings['about_slug'] ?? 'about', 'about'),
            'contact' => $this->safeSlug($settings['contact_slug'] ?? 'contact', 'contact'),
        ];
    }

    private function safeSlug(string $value, string $default): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? $default;
        $value = trim($value, '-');

        return $value !== '' ? $value : $default;
    }
}

// End of file.
