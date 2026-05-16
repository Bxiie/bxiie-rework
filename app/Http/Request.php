<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Represents the current HTTP request.
 */
final class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $host,
        private readonly array $query,
        private readonly array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            method: strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            path: $path ?: '/',
            host: (string) ($_SERVER['HTTP_HOST'] ?? ''),
            query: $_GET,
            server: $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return isset($this->query[$key]) ? (string) $this->query[$key] : $default;
    }

    public function server(string $key, ?string $default = null): ?string
    {
        return isset($this->server[$key]) ? (string) $this->server[$key] : $default;
    }
}

// End of file.
