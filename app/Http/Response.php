<?php

/**
 * HTTP response container and emitter.
 */

declare(strict_types=1);

namespace App\Http;

use App\Http\View\ErrorPage;

/**
 * Builds and sends HTTP responses.
 *
 * Header values may be strings or arrays of strings. Array values are emitted
 * as repeated header lines, which is required for multiple Set-Cookie headers.
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

    public static function notFound(string $message = 'The page you requested could not be found.'): self
    {
        return self::html(ErrorPage::notFound($message), 404);
    }

    public static function error(int $statusCode, string $message = ''): self
    {
        return self::html(ErrorPage::status($statusCode, $message), $statusCode);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            foreach ((array) $value as $headerValue) {
                header("{$name}: {$headerValue}", false);
            }
        }

        echo $this->body;
    }
}

// End of file.
