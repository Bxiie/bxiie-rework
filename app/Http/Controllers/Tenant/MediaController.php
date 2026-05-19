<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use PDO;

final class MediaController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function show(Request $request, TenantContext $tenant): Response
    {
        $mediaId = (int) ($_GET['id'] ?? 0);

        if ($mediaId <= 0) {
            return Response::html('<h1>404</h1><p>Media not found.</p>', 404);
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM media_assets
             WHERE tenant_id = :tenant_id
               AND id = :media_id
               AND is_private = 0
             LIMIT 1"
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'media_id' => $mediaId,
        ]);

        $media = $stmt->fetch();

        if (!$media) {
            return Response::html('<h1>404</h1><p>Media not found.</p>', 404);
        }

        $relativePath = ltrim((string) $media['storage_path'], '/');
        $absolute = dirname(__DIR__, 3) . '/' . $relativePath;

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
