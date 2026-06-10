<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;

/**
 * Resolves the current browser user from the artsfolio_session cookie.
 */
final class CurrentUser
{
    public const COOKIE_NAME = 'artsfolio_session';

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly SessionTokenService $tokens,
    ) {
    }

    public function resolve(Request $request): ?array
    {
        $rawToken = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!$rawToken || !is_string($rawToken)) {
            return null;
        }

        return $this->sessions->findActiveByHash($this->tokens->hashToken($rawToken));
    }
}

// End of file.
