<?php

declare(strict_types=1);

namespace App\Http;


use App\Http\View\ErrorPage;
/**
 * Minimal HTTP router supporting exact routes and simple {parameter} path segments.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][] = [
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->compilePattern($path),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $match = $this->match($request->method(), $request->path());

        if (!$match) {
            return Response::notFound("No route for {$request->method()} {$request->path()}");
        }

        $response = ($match->handler)($request, $match->parameters);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Route handlers must return App\Http\Response.');
        }

        return $response;
    }

    private function match(string $method, string $path): ?RouteMatch
    {
        foreach ($this->routes[strtoupper($method)] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $parameters = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[$key] = $value;
                }
            }

            return new RouteMatch($route['handler'], $parameters);
        }

        return null;
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $matches): string => '(?P<' . $matches[1] . '>[^/]+)',
            $path
        );

        return '#^' . $pattern . '$#';
    }
}

// End of file.
