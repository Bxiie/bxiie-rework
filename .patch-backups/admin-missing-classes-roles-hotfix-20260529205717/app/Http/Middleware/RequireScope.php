<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/**
 * Validates OAuth2 bearer token scopes for API routes.
 */
final class RequireScope
{
    public function hasScope(?array $accessToken, string $requiredScope): bool
    {
        if (!$accessToken) {
            return false;
        }

        $scopes = json_decode((string) ($accessToken['scopes'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($scopes)) {
            return false;
        }

        return in_array($requiredScope, $scopes, true);
    }
}

// End of file.
