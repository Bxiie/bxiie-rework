<?php

declare(strict_types=1);

namespace App\Platform\Auth;

use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;
use PDO;

/** Resolves the correct administrative landing page after authentication. */
final class PostLoginDestination
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MembershipRepository $memberships,
    ) {
    }

    public function forUser(int $userId, ?TenantContext $currentTenant = null): string
    {
        if ($currentTenant !== null && $this->memberships->tenantRolesForUser($currentTenant->tenantId, $userId) !== []) {
            return '/admin';
        }

        if ($this->memberships->platformRolesForUser($userId) !== []) {
            return $currentTenant === null ? '/platform/admin' : 'https://artsfol.io/platform/admin';
        }

        $stmt = $this->pdo->prepare(
            "SELECT t.slug
               FROM tenant_memberships tm
               JOIN tenants t ON t.id = tm.tenant_id
               JOIN role_assignments ra ON ra.tenant_id = tm.tenant_id AND ra.user_id = tm.user_id
               JOIN roles r ON r.id = ra.role_id AND r.scope = 'tenant'
              WHERE tm.user_id = :user_id
                AND tm.status = 'active'
                AND t.status IN ('trial', 'active')
              ORDER BY (r.slug = 'owner') DESC, tm.tenant_id ASC
              LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $slug = trim((string) ($stmt->fetchColumn() ?: ''));

        return $slug !== '' ? 'https://' . $slug . '.artsfol.io/admin' : '/';
    }
}

// End of file.
