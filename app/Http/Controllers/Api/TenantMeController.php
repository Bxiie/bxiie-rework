<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\RequireScope;
use App\Http\Middleware\RequireTenantAccess;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;

/**
 * Handles tenant-scoped API current-user responses.
 */
final class TenantMeController
{
    public function __construct(
        private readonly RequireScope $scopes = new RequireScope(),
        private readonly RequireTenantAccess $tenantAccess = new RequireTenantAccess(),
    ) {
    }

    public function show(Request $request, ?array $accessToken, TenantContext $tenant): Response
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

        if (!$this->tenantAccess->allows($accessToken, $tenant)) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Bearer token is not valid for this tenant.',
            ], 403);
        }

        return Response::json([
            'user' => [
                'id' => (int) $accessToken['user_id'],
                'email' => (string) $accessToken['email'],
                'display_name' => $accessToken['display_name'],
            ],
            'tenant' => [
                'id' => $tenant->tenantId,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'hostname' => $tenant->hostname,
            ],
            'token_tenant_id' => $accessToken['tenant_id'] !== null ? (int) $accessToken['tenant_id'] : null,
            'scopes' => json_decode((string) $accessToken['scopes'], true, 512, JSON_THROW_ON_ERROR),
        ]);
    }
}

// End of file.
