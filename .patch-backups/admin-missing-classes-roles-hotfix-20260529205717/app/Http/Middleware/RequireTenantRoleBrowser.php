<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;

/**
 * Validates tenant role access for browser/admin routes using the current session user.
 */
final class RequireTenantRoleBrowser
{
    public function __construct(
        private readonly MembershipRepository $memberships,
    ) {
    }

    public function allows(?array $currentUser, TenantContext $tenant, array $allowedRoles): bool
    {
        if (!$currentUser || empty($currentUser['user_id'])) {
            return false;
        }

        $roles = $this->memberships->tenantRolesForUser(
            tenantId: $tenant->tenantId,
            userId: (int) $currentUser['user_id'],
        );

        return count(array_intersect($roles, $allowedRoles)) > 0;
    }
}

// End of file.
