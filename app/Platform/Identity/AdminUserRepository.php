<?php

/**
 * Platform and tenant user-management repository.
 */

declare(strict_types=1);

namespace App\Platform\Identity;

use PDO;

/**
 * Read/write helpers for admin user-management screens.
 */
final class AdminUserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function tenantUsers(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id,
                u.uuid,
                u.email,
                u.display_name,
                'active' AS user_status,
                u.created_at,
                tm.status AS membership_status,
                GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ', ') AS roles,
                MAX(us.created_at) AS last_login_at
             FROM tenant_memberships tm
             JOIN users u ON u.id = tm.user_id
             LEFT JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id = tm.tenant_id
             LEFT JOIN roles r ON r.id = ra.role_id AND r.scope = 'tenant'
             LEFT JOIN user_sessions us ON us.user_id = u.id
             WHERE tm.tenant_id = :tenant_id
             GROUP BY u.id, u.uuid, u.email, u.display_name, u.created_at, tm.status
             ORDER BY u.email"
        );

        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public function platformUsers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                u.id,
                u.uuid,
                u.email,
                u.display_name,
                'active' AS user_status,
                u.created_at,
                GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ', ') AS roles,
                MAX(us.created_at) AS last_login_at
             FROM users u
             JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id IS NULL
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'platform'
             LEFT JOIN user_sessions us ON us.user_id = u.id
             GROUP BY u.id, u.uuid, u.email, u.display_name, u.created_at
             ORDER BY u.email"
        );

        return $stmt->fetchAll();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET password_hash = :password_hash,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id"
        );

        $stmt->execute(['password_hash' => $passwordHash, 'user_id' => $userId]);
    }

    public function setUserStatus(int $userId, string $status): void
    {
        if (!in_array($status, ['active', 'suspended', 'deleted'], true)) {
            throw new \InvalidArgumentException('Invalid user status.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id"
        );
        $stmt->execute(['status' => $status, 'user_id' => $userId]);

        if ($status !== 'active') {
            $this->revokeUserSessions($userId);
        }
    }

    public function userBelongsToTenant(int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM tenant_memberships
             WHERE tenant_id = :tenant_id AND user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    public function userIsPlatformUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'platform'
             WHERE ra.user_id = :user_id AND ra.tenant_id IS NULL
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }



    /**
     * Creates or reuses a user and assigns the platform admin role in invited status.
     */
    public function invitePlatformUser(string $email, ?string $displayName = null): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid invite email address is required.');
        }

        $this->pdo->beginTransaction();
        try {
            $userId = $this->findOrCreateUser($email, $displayName);
            $this->assignPlatformRole($userId, 'admin');
            $this->pdo->commit();
            return $userId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Creates or reuses a user, attaches them to a tenant, assigns tenant admin,
     * and leaves membership in invited status until the user accepts.
     */
    public function inviteTenantAdmin(int $tenantId, string $email, ?string $displayName = null): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid invite email address is required.');
        }

        $this->pdo->beginTransaction();
        try {
            $userId = $this->findOrCreateUser($email, $displayName);
            $this->attachTenantMembership($tenantId, $userId, 'invited');
            $this->assignTenantRole($tenantId, $userId, 'admin');
            $this->pdo->commit();
            return $userId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Promotes a tenant member to tenant owner while preserving existing admin role.
     */
    public function promoteTenantUserToOwner(int $tenantId, int $userId): void
    {
        if (!$this->userBelongsToTenant($tenantId, $userId)) {
            throw new \InvalidArgumentException('User does not belong to this tenant.');
        }

        $this->assignTenantRole($tenantId, $userId, 'owner');
    }

    /**
     * Removes a user from one tenant and revokes tenant-scoped roles for that tenant.
     */
    public function deleteTenantUser(int $tenantId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $roles = $this->pdo->prepare('DELETE FROM role_assignments WHERE tenant_id = :tenant_id AND user_id = :user_id');
            $roles->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);

            $membership = $this->pdo->prepare('DELETE FROM tenant_memberships WHERE tenant_id = :tenant_id AND user_id = :user_id');
            $membership->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);

            $this->revokeUserSessions($userId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function findOrCreateUser(string $email, ?string $displayName): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO users (uuid, email, display_name, created_at, updated_at)
             VALUES (:uuid, :email, :display_name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $insert->execute([
            'uuid' => $this->uuidV4(),
            'email' => $email,
            'display_name' => $displayName,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function attachTenantMembership(int $tenantId, int $userId, string $status): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_memberships (tenant_id, user_id, status, created_at, updated_at)
             VALUES (:tenant_id, :user_id, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId, 'status' => $status]);
    }


    /**
     * Assigns a platform-scoped role to a user exactly once.
     *
     * MySQL unique indexes allow multiple NULL tenant_id values, so platform
     * assignments must check for an existing row before inserting rather than
     * relying on INSERT IGNORE against uq_role_assignment.
     */
    private function assignPlatformRole(int $userId, string $roleSlug): void
    {
        $roleId = $this->roleId('platform', $roleSlug);

        $existing = $this->pdo->prepare(
            "SELECT 1
             FROM role_assignments
             WHERE role_id = :role_id
               AND user_id = :user_id
               AND tenant_id IS NULL
             LIMIT 1"
        );
        $existing->execute(['role_id' => $roleId, 'user_id' => $userId]);
        if ($existing->fetchColumn()) {
            return;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO role_assignments (role_id, user_id, tenant_id, created_at)
             VALUES (:role_id, :user_id, NULL, CURRENT_TIMESTAMP)"
        );
        $insert->execute(['role_id' => $roleId, 'user_id' => $userId]);
    }

    private function assignTenantRole(int $tenantId, int $userId, string $roleSlug): void
    {
        $roleId = $this->roleId('tenant', $roleSlug);
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO role_assignments (role_id, user_id, tenant_id, created_at)
             VALUES (:role_id, :user_id, :tenant_id, CURRENT_TIMESTAMP)"
        );
        $stmt->execute(['role_id' => $roleId, 'user_id' => $userId, 'tenant_id' => $tenantId]);
    }

    private function roleId(string $scope, string $slug): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE scope = :scope AND slug = :slug LIMIT 1');
        $stmt->execute(['scope' => $scope, 'slug' => $slug]);
        $roleId = $stmt->fetchColumn();
        if (!$roleId) {
            throw new \RuntimeException("Missing {$scope} role: {$slug}");
        }

        return (int) $roleId;
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function revokeUserSessions(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id
               AND revoked_at IS NULL"
        );
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Suspends a user without deleting audit history.
     */
    public function suspendUser(int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET status = 'suspended', suspended_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Soft-deletes a user. Historical records are retained.
     */
    public function deleteUser(int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET status = 'deleted', deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
        $stmt->execute(['user_id' => $userId]);
    }
}

// End of file.
