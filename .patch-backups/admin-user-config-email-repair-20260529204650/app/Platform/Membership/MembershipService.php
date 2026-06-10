<?php

declare(strict_types=1);

namespace App\Platform\Membership;

/**
 * Coordinates tenant membership lifecycle operations.
 */
final class MembershipService
{
    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    public function addTenantOwner(int $tenantId, int $userId): void
    {
        $this->memberships->addTenantMembership($tenantId, $userId);
        $this->memberships->assignRole(Roles::TENANT_SCOPE, Roles::TENANT_OWNER, $userId, $tenantId);
    }

    public function addTenantAdmin(int $tenantId, int $userId): void
    {
        $this->memberships->addTenantMembership($tenantId, $userId);
        $this->memberships->assignRole(Roles::TENANT_SCOPE, Roles::TENANT_ADMIN, $userId, $tenantId);
    }
}

// End of file.
