<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Platform\Auth\OAuth\BearerTokenRepository;
use App\Platform\Auth\OAuth\BearerTokenService;

/**
 * Resolves API users from OAuth2 bearer access tokens.
 */
final class BearerTokenAuth
{
    public function __construct(
        private readonly BearerTokenRepository $tokens,
        private readonly BearerTokenService $tokenService,
    ) {
    }

    public function resolve(Request $request): ?array
    {
        $header = $request->server('HTTP_AUTHORIZATION')
            ?? $request->server('REDIRECT_HTTP_AUTHORIZATION');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $rawToken = trim(substr($header, 7));

        if ($rawToken === '') {
            return null;
        }

        return $this->tokens->findActiveByTokenHash($this->tokenService->hashToken($rawToken));
    }
}

// End of file.
