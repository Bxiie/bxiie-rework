<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use App\Platform\Tenancy\TenantContext;
use PDO;
use Throwable;

/**
 * Central tenant-scoped authorization for social publishing.
 */
final class SocialPermissionService
{
    public const PUBLISH_PERMISSION = 'social.publish';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isTenantAdmin(?array $currentUser, TenantContext $tenant): bool
    {
        $userId = (int) ($currentUser['user_id'] ?? 0);
        return $userId > 0
            && $this->hasTenantRole($tenant->tenantId, $userId, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }

    public function canPublish(?array $currentUser, TenantContext $tenant): bool
    {
        $userId = (int) ($currentUser['user_id'] ?? 0);
        if ($userId < 1) {
            return false;
        }

        // Tenant owners/admins use the same role-based authorization contract as
        // ArtsFolio's canonical tenant-admin middleware. Legacy tenants may have
        // valid role assignments without a tenant_memberships row, so requiring
        // membership here would incorrectly hide social controls from an admin
        // who is already authorized everywhere else under /admin.
        if ($this->isTenantAdmin($currentUser, $tenant)) {
            return true;
        }

        // Editors are intentionally narrower: they must remain active tenant
        // members, hold the editor role, and have the explicit social capability.
        if (!$this->activeTenantMembership($tenant->tenantId, $userId)) {
            return false;
        }

        if (!$this->hasTenantRole($tenant->tenantId, $userId, ['editor'])) {
            return false;
        }

        return $this->hasExplicitPermission($tenant->tenantId, $userId, self::PUBLISH_PERMISSION);
    }

    public function setEditorPermission(int $tenantId, int $userId, bool $enabled, int $grantedByUserId): void
    {
        if (!$this->hasTenantRole($tenantId, $userId, ['editor'])) {
            if (!$enabled) {
                $this->deletePermission($tenantId, $userId, self::PUBLISH_PERMISSION);
                return;
            }
            throw new \InvalidArgumentException('Social publishing permission may only be assigned to a tenant editor.');
        }

        if ($enabled) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO tenant_user_permissions (tenant_id, user_id, permission_key, granted_by_user_id, created_at)
                 VALUES (:tenant_id, :user_id, :permission_key, :granted_by_user_id, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE granted_by_user_id = VALUES(granted_by_user_id)"
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'permission_key' => self::PUBLISH_PERMISSION,
                'granted_by_user_id' => $grantedByUserId,
            ]);
            return;
        }

        $this->deletePermission($tenantId, $userId, self::PUBLISH_PERMISSION);
    }

    public function editorHasPermission(int $tenantId, int $userId): bool
    {
        return $this->hasExplicitPermission($tenantId, $userId, self::PUBLISH_PERMISSION);
    }

    private function activeTenantMembership(int $tenantId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM tenant_memberships
                 WHERE tenant_id = :tenant_id
                   AND user_id = :user_id
                   AND status = 'active'
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /** @param list<string> $roles */
    private function hasTenantRole(int $tenantId, int $userId, array $roles): bool
    {
        if ($roles === []) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'tenant'
             WHERE ra.tenant_id = ?
               AND ra.user_id = ?
               AND r.slug IN ({$placeholders})
             LIMIT 1"
        );
        $stmt->execute(array_merge([$tenantId, $userId], $roles));
        return (bool) $stmt->fetchColumn();
    }

    private function hasExplicitPermission(int $tenantId, int $userId, string $permission): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM tenant_user_permissions
                 WHERE tenant_id = :tenant_id
                   AND user_id = :user_id
                   AND permission_key = :permission_key
                 LIMIT 1"
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'permission_key' => $permission,
            ]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function deletePermission(int $tenantId, int $userId, string $permission): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM tenant_user_permissions WHERE tenant_id = :tenant_id AND user_id = :user_id AND permission_key = :permission_key'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'permission_key' => $permission,
        ]);
    }
}

// End of file.
