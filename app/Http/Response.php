<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Builds and sends HTTP responses.
 */
final class Response
{
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, array_merge([
            'Content-Type' => 'text/html; charset=utf-8',
        ], $headers));
    }

    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            $status,
            array_merge([
                'Content-Type' => 'application/json; charset=utf-8',
            ], $headers),
        );
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return self::html("<h1>404</h1>\n<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>\n", 404);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}

// End of file.
