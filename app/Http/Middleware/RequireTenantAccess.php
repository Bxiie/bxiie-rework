<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Tenancy\TenantContext;

/**
 * Validates whether an API access token is allowed to act within a tenant context.
 */
final class RequireTenantAccess
{
    public function allows(?array $accessToken, TenantContext $tenant): bool
    {
        if (!$accessToken) {
            return false;
        }

        if ($accessToken['tenant_id'] === null) {
            return true;
        }

        return (int) $accessToken['tenant_id'] === $tenant->tenantId;
    }
}

// End of file.
