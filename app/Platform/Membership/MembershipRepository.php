<?php

declare(strict_types=1);

namespace App\Platform\Membership;

use PDO;

/**
 * Handles tenant membership and role assignment persistence.
 */
final class MembershipRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function addTenantMembership(int $tenantId, int $userId, string $status = 'active'): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_memberships (tenant_id, user_id, status, updated_at)
             VALUES (:tenant_id, :user_id, :status, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'status' => $status,
        ]);
    }

    public function assignRole(string $scope, string $roleSlug, int $userId, ?int $tenantId = null): void
    {
        $role = $this->findRole($scope, $roleSlug);

        if (!$role) {
            throw new \RuntimeException("Role not found for scope={$scope}, slug={$roleSlug}");
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO role_assignments (role_id, user_id, tenant_id)
             VALUES (:role_id, :user_id, :tenant_id)
             ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)"
        );

        $stmt->execute([
            'role_id' => (int) $role['id'],
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);
    }

    public function tenantRolesForUser(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT r.slug FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id
             WHERE ra.user_id = :user_id AND ra.tenant_id = :tenant_id AND r.scope = 'tenant'
             ORDER BY r.slug"
        );

        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);

        return array_map(static fn (array $row): string => (string) $row['slug'], $stmt->fetchAll());
    }


    public function platformRolesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT r.slug
             FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id
             WHERE ra.user_id = :user_id
               AND ra.tenant_id IS NULL
               AND r.scope = 'platform'
             ORDER BY r.slug"
        );

        $stmt->execute(['user_id' => $userId]);

        return array_map(static fn (array $row): string => (string) $row['slug'], $stmt->fetchAll());
    }

    private function findRole(string $scope, string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE scope = :scope AND slug = :slug LIMIT 1");
        $stmt->execute(['scope' => $scope, 'slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

// End of file.
