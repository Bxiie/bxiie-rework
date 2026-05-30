<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Membership\MembershipRepository;

/**
 * Validates platform role access for browser/admin routes.
 */
final class RequirePlatformRole
{
    public function __construct(
        private readonly MembershipRepository $memberships,
    ) {
    }

    public function allows(?array $currentUser, array $allowedRoles): bool
    {
        if (!$currentUser || empty($currentUser['user_id'])) {
            return false;
        }

        $roles = $this->memberships->platformRolesForUser((int) $currentUser['user_id']);

        return count(array_intersect($roles, $allowedRoles)) > 0;
    }
}

// End of file.
