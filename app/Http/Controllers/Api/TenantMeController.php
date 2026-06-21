<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\RequireScope;
use App\Http\Middleware\RequireTenantAccess;
use App\Http\Middleware\RequireTenantRole;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;

/**
 * Handles tenant-scoped API current-user responses.
 */
final class TenantMeController
{
    public function __construct(
        private readonly RequireScope $scopes = new RequireScope(),
        private readonly RequireTenantAccess $tenantAccess = new RequireTenantAccess(),
        private readonly ?RequireTenantRole $tenantRoles = null,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function show(Request $request, ?array $accessToken, TenantContext $tenant): Response
    {
        if (!$accessToken) {
            $this->auditDenied($request, $tenant, null, 'api.tenant_me.denied.missing_token');

            return Response::json([
                'error' => 'unauthorized',
                'message' => 'Missing, expired, or revoked bearer token.',
            ], 401);
        }

        if (!$this->scopes->hasScope($accessToken, 'api:read')) {
            $this->auditDenied($request, $tenant, $accessToken, 'api.tenant_me.denied.missing_scope');

            return Response::json([
                'error' => 'forbidden',
                'message' => 'Bearer token does not include required scope: api:read.',
            ], 403);
        }

        if (!$this->tenantAccess->allows($accessToken, $tenant)) {
            $this->auditDenied($request, $tenant, $accessToken, 'api.tenant_me.denied.tenant_mismatch');

            return Response::json([
                'error' => 'forbidden',
                'message' => 'Bearer token is not valid for this tenant.',
            ], 403);
        }

        if ($this->tenantRoles && !$this->tenantRoles->allows($accessToken, $tenant, ['owner', 'admin', 'editor', 'viewer'])) {
            $this->auditDenied($request, $tenant, $accessToken, 'api.tenant_me.denied.missing_membership_role');

            return Response::json([
                'error' => 'forbidden',
                'message' => 'User does not have tenant membership access.',
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

    private function auditDenied(Request $request, TenantContext $tenant, ?array $accessToken, string $action): void
    {
        if (!$this->auditLog || $this->isTrustedLocalSmokeProbe($request)) {
            return;
        }

        $this->auditLog->record(
            action: $action,
            tenantId: $tenant->tenantId,
            userId: isset($accessToken['user_id']) ? (int) $accessToken['user_id'] : null,
            entityType: 'api_route',
            entityId: '/api/me',
            details: [
                'host' => $request->host(),
                'path' => $request->path(),
                'token_tenant_id' => $accessToken['tenant_id'] ?? null,
            ],
            ipAddress: $request->server('REMOTE_ADDR'),
        );
    }
    /**
     * Suppress only deliberate localhost smoke probes carrying the private test marker.
     */
    private function isTrustedLocalSmokeProbe(Request $request): bool
    {
        $ip = trim((string) $request->server('REMOTE_ADDR', ''));
        $marker = trim((string) $request->server('HTTP_X_ARTSFOLIO_TEST_PROBE', ''));

        return in_array($ip, ['127.0.0.1', '::1'], true)
            && hash_equals('http-smoke', $marker);
    }

}

// End of file.
