<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Minimal exact-match HTTP router.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if (!$handler) {
            return Response::notFound("No route for {$request->method()} {$request->path()}");
        }

        $response = $handler($request);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Route handlers must return App\Http\Response.');
        }

        return $response;
    }
}

// End of file.
