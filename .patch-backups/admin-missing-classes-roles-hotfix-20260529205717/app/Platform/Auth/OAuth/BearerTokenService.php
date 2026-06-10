<?php

declare(strict_types=1);

namespace App\Platform\Auth\OAuth;

/**
 * Handles raw OAuth2 bearer token hashing and extraction support.
 */
final class BearerTokenService
{
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateDevelopmentToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

// End of file.
