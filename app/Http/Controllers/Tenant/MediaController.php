<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use PDO;

final class MediaController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?RequireTenantRoleBrowser $roles = null,
    ) {
    }

    public function public(Request $request, TenantContext $tenant): Response
    {
        return $this->serve($tenant, requirePublishedArtwork: true);
    }

    public function admin(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if ($this->roles === null || !$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        return $this->serve($tenant, requirePublishedArtwork: false);
    }

    private function serve(TenantContext $tenant, bool $requirePublishedArtwork): Response
    {
        $mediaUuid = strtolower(trim((string) ($_GET['uuid'] ?? '')));

        if (!preg_match('/^[a-f0-9-]{36}$/', $mediaUuid)) {
            return Response::html('<h1>404</h1><p>Media not found.</p>', 404);
        }

        $sql = "SELECT m.*
                FROM media_assets m";

        if ($requirePublishedArtwork) {
            $sql .= " JOIN artworks a
                        ON a.primary_media_id = m.id
                       AND a.tenant_id = m.tenant_id
                       AND a.status = 'published'";
        }

        $sql .= " WHERE m.tenant_id = :tenant_id
                    AND m.uuid = :media_uuid
                    AND m.is_private = 0
                  LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'media_uuid' => $mediaUuid,
        ]);

        $media = $stmt->fetch();

        if (!$media) {
            return Response::html('<h1>404</h1><p>Media not found.</p>', 404);
        }

        $absolute = dirname(__DIR__, 4) . '/' . ltrim((string) $media['storage_path'], '/');

        if (!is_file($absolute)) {
            return Response::html('<h1>404</h1><p>Media file missing.</p>', 404);
        }

        header('Content-Type: ' . ($media['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($absolute));
        header('Cache-Control: public, max-age=86400');
        readfile($absolute);
        exit;
    }
}

// End of file.
