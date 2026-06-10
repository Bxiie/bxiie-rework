<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;

/**
 * Validates tenant membership and role access for authenticated users.
 */
final class RequireTenantRole
{
    public function __construct(
        private readonly MembershipRepository $memberships,
    ) {
    }

    public function allows(?array $accessToken, TenantContext $tenant, array $allowedRoles): bool
    {
        if (!$accessToken || empty($accessToken['user_id'])) {
            return false;
        }

        $roles = $this->memberships->tenantRolesForUser(
            tenantId: $tenant->tenantId,
            userId: (int) $accessToken['user_id'],
        );

        return count(array_intersect($roles, $allowedRoles)) > 0;
    }
}

// End of file.
