<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Social\SocialPermissionService;
use PDO;

/**
 * Tenant-admin-only editor capability management for social publishing.
 */
final class SocialPermissionAdminController
{
    private PDO $pdo;

    public function __construct(private readonly string $root)
    {
        $this->pdo = Database::connect($root);
    }

    public function handle(Request $request): ?Response
    {
        if ($request->path() !== '/admin/social/editor-permission') {
            return null;
        }

        $tenant = (new TenantResolver($this->pdo))->resolveFromHost($request->host());
        if (!$tenant) {
            return Response::notFound();
        }
        $currentUser = (new CurrentUser(new SessionRepository($this->pdo), new SessionTokenService()))->resolve($request);
        $adminUserId = (int) ($currentUser['user_id'] ?? 0);
        if ($adminUserId < 1 || !$this->isTenantAdmin($tenant->tenantId, $adminUserId)) {
            return Response::error(403, 'Tenant admin access required.');
        }

        $targetUserId = max(0, (int) ($_REQUEST['user_id'] ?? 0));
        if ($targetUserId < 1 || !$this->tenantEditor($tenant->tenantId, $targetUserId)) {
            return Response::error(422, 'A tenant editor is required.');
        }

        $permissions = new SocialPermissionService($this->pdo);
        if ($request->method() === 'GET') {
            return Response::json([
                'ok' => true,
                'user_id' => $targetUserId,
                'social_publish' => $permissions->editorHasPermission($tenant->tenantId, $targetUserId),
            ], 200, ['Cache-Control' => 'private, no-store']);
        }

        if ($request->method() !== 'POST') {
            return Response::error(405, 'Method not allowed.');
        }
        if (!(new CsrfTokenService())->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $enabled = (string) ($_POST['enabled'] ?? '') === '1';
        $permissions->setEditorPermission($tenant->tenantId, $targetUserId, $enabled, $adminUserId);
        return Response::json([
            'ok' => true,
            'user_id' => $targetUserId,
            'social_publish' => $enabled,
            'message' => $enabled ? 'Social publishing permission granted.' : 'Social publishing permission removed.',
        ]);
    }

    private function isTenantAdmin(int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM tenant_memberships tm
             JOIN role_assignments ra ON ra.tenant_id = tm.tenant_id AND ra.user_id = tm.user_id
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'tenant'
             WHERE tm.tenant_id = :tenant_id
               AND tm.user_id = :user_id
               AND tm.status = 'active'
               AND r.slug IN ('owner','admin','tenant_owner','tenant_admin')
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private function tenantEditor(int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM tenant_memberships tm
             JOIN role_assignments ra ON ra.tenant_id = tm.tenant_id AND ra.user_id = tm.user_id
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'tenant'
             WHERE tm.tenant_id = :tenant_id
               AND tm.user_id = :user_id
               AND tm.status = 'active'
               AND r.slug = 'editor'
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);
        return (bool) $stmt->fetchColumn();
    }
}

// End of file.
