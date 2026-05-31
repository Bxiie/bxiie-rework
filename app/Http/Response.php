<?php

/**
 * Main HTTP response object used by the hand-rolled router.
 */

declare(strict_types=1);

namespace App\Http;

use App\Http\View\ErrorPage;

/**
 * Builds and sends HTTP responses.
 *
 * Header values may be strings or string arrays. Array support is required for
 * Set-Cookie because browsers need separate Set-Cookie header lines; combining
 * cookies into a comma-delimited header corrupts cookie semantics.
 */
final class Response
{
    /**
     * @param array<string, string|array<int, string>> $headers
     */
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    /**
     * @param array<string, string|array<int, string>> $headers
     */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, array_merge([
            'Content-Type' => 'text/html; charset=utf-8',
        ], $headers));
    }

    /**
     * @param array<string, string|array<int, string>> $headers
     */
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

    public static function notFound(string $message = 'Page not found'): self
    {
        return self::html(ErrorPage::notFound($message), 404);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            foreach ((array) $value as $headerValue) {
                $replace = strtolower((string) $name) !== 'set-cookie';
                header("{$name}: {$headerValue}", $replace);
            }
        }

        echo $this->body;
    }
}

// End of file.
