<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Generates and validates simple CSRF tokens for browser form posts.
 */
final class CsrfTokenService
{
    public const SESSION_KEY = 'csrf_token';

    public function getOrCreate(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('PHP session must be active before using CSRF tokens.');
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('PHP session must be active before validating CSRF tokens.');
        }

        if (!$token || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::SESSION_KEY], $token);
    }
}

// End of file.
