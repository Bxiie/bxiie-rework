<?php

declare(strict_types=1);

namespace App\Platform\Auth\Session;

/**
 * Generates and hashes opaque session tokens.
 */
final class SessionTokenService
{
    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}

// End of file.
