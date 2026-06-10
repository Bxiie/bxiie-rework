<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\RequireScope;
use App\Http\Request;
use App\Http\Response;

/**
 * Handles API current-user responses.
 */
final class MeController
{
    public function __construct(
        private readonly RequireScope $scopes = new RequireScope(),
    ) {
    }

    public function show(Request $request, ?array $accessToken): Response
    {
        if (!$accessToken) {
            return Response::json([
                'error' => 'unauthorized',
                'message' => 'Missing, expired, or revoked bearer token.',
            ], 401);
        }

        if (!$this->scopes->hasScope($accessToken, 'api:read')) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Bearer token does not include required scope: api:read.',
            ], 403);
        }

        return Response::json([
            'user' => [
                'id' => (int) $accessToken['user_id'],
                'email' => (string) $accessToken['email'],
                'display_name' => $accessToken['display_name'],
            ],
            'tenant_id' => $accessToken['tenant_id'] !== null ? (int) $accessToken['tenant_id'] : null,
            'scopes' => json_decode((string) $accessToken['scopes'], true, 512, JSON_THROW_ON_ERROR),
            'expires_at' => $accessToken['expires_at'],
        ]);
    }
}

// End of file.
