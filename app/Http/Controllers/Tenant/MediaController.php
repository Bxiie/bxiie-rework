<?php

// Serves tenant-scoped media files with support for generated variants.

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\View\ErrorPage;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use PDO;
use App\Tenant\Media\WatermarkService;
use App\Tenant\Settings\TenantSettingsRepository;

final class MediaController
{
    private const ALLOWED_VARIANTS = ['thumb', 'medium', 'large', 'original'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?RequireTenantRoleBrowser $roles = null,
    ) {
    }

    public function public(
        Request $request,
        TenantContext $tenant,
        ?array $currentUser = null,
    ): Response {
        $allowUnpublishedPreview = (string) ($_GET['preview_unpublished'] ?? '') === '1'
            && $this->roles !== null
            && $this->roles->allows(
                $currentUser,
                $tenant,
                ['tenant_owner', 'tenant_admin', 'owner', 'admin'],
            );

        return $this->serve(
            $tenant,
            requirePublishedArtwork: !$allowUnpublishedPreview,
            allowSelectedBackground: true,
            applyPublicWatermark: true,
        );
    }

    public function admin(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if ($this->roles === null || !$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        return $this->serve($tenant, requirePublishedArtwork: false);
    }

    private function serve(
        TenantContext $tenant,
        bool $requirePublishedArtwork,
        bool $allowSelectedBackground = false,
        bool $applyPublicWatermark = false,
    ): Response
    {
        $mediaUuid = strtolower(trim((string) ($_GET['uuid'] ?? '')));
        $variantKey = $this->requestedVariant();
        $isBackgroundRequest = strtolower(
            trim((string) ($_GET['usage'] ?? ''))
        ) === 'background';

        if (!preg_match('/^[a-f0-9-]{36}$/', $mediaUuid)) {
            return Response::html('<h1>404</h1><p>Media not found.</p>', 404);
        }

        $media = $this->findMedia($tenant, $mediaUuid, $requirePublishedArtwork);

        if (
            !$media
            && $allowSelectedBackground
            && $this->isSelectedBackground($tenant, $mediaUuid)
        ) {
            $media = $this->findMedia($tenant, $mediaUuid, false);
            $isBackgroundRequest = true;
        }

        if (!$media) {
            return Response::html('<h1>404</h1><p>Media not found.</p>', 404);
        }

        $variant = $this->findVariant((int) $media['id'], $variantKey)
            ?? $this->findVariant((int) $media['id'], $this->fallbackVariant($variantKey))
            ?? $this->originalVariant($media);

        $absolute = dirname(__DIR__, 4) . '/' . ltrim((string) $variant['storage_path'], '/');

        if (!is_file($absolute)) {
            return Response::html('<h1>404</h1><p>Media file missing.</p>', 404);
        }

        $mimeType = (string) ($variant['mime_type'] ?: $media['mime_type'] ?: 'application/octet-stream');
        $watermark = new WatermarkService(
            new TenantSettingsRepository($this->pdo),
            $this->pdo,
        );

        // Thumbnails intentionally remain unwatermarked. Public medium, large,
        // and original artwork responses use the tenant watermark setting.
        $watermarkEnabled = $applyPublicWatermark
            && !$isBackgroundRequest
            && !$this->isSelectedPresentationMedia(
                $tenant,
                $mediaUuid,
            )
            && $variantKey !== 'thumb'
            && $watermark->enabled($tenant);
        $watermarkFingerprint = $watermarkEnabled
            ? $watermark->fingerprint($tenant)
            : 'disabled';

        $etag = '"' . sha1(
            (string) $media['uuid']
            . ':' . $variant['variant_key']
            . ':' . (string) filemtime($absolute)
            . ':' . $watermarkFingerprint
        ) . '"';

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }

        $bytes = null;
        $watermarkCachePath = null;
        if ($watermarkEnabled) {
            $cacheDir = dirname(__DIR__, 4) . '/storage/cache/watermarks/' . $tenant->tenantId;
            $watermarkCachePath = $cacheDir . '/' . trim($etag, '"') . '.bin';
            if (is_file($watermarkCachePath)) {
                $bytes = file_get_contents($watermarkCachePath);
            } else {
                $bytes = $watermark->render($tenant, $absolute, $mimeType);
                if ($bytes !== null) {
                    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
                        error_log('ArtsFolio could not create watermark cache directory: ' . $cacheDir);
                    } elseif (file_put_contents($watermarkCachePath, $bytes, LOCK_EX) === false) {
                        error_log('ArtsFolio could not write watermark cache file: ' . $watermarkCachePath);
                    }
                }
            }
        }

        $watermarkStatus = 'disabled';

        if ($watermarkEnabled) {
            $watermarkStatus = $bytes !== null
                ? 'rendered'
                : 'unavailable';

            if ($bytes === null) {
                error_log(
                    'ArtsFolio watermark unavailable for tenant '
                    . $tenant->tenantId
                    . '; verify PHP GD, FreeType, and image format support.'
                );
            }
        } elseif (
            $applyPublicWatermark
            && $variantKey === 'thumb'
        ) {
            $watermarkStatus = 'thumbnail-excluded';
        }

        header('Content-Type: ' . $mimeType);
        header(
            'Content-Length: '
            . ($bytes !== null ? strlen($bytes) : filesize($absolute))
        );
        header(
            'Cache-Control: '
            . (
                $watermarkEnabled
                    ? 'public, max-age=86400, immutable'
                    : 'public, max-age=86400'
            )
        );
        header('ETag: ' . $etag);
        // Unwatermarked media policy: Cache-Control: public, max-age=86400
        header('X-ArtsFolio-Watermark: ' . $watermarkStatus);

        if ($bytes !== null) { echo $bytes; } else { readfile($absolute); }
        exit;
    }

    private function requestedVariant(): string
    {
        $variant = strtolower(trim((string) ($_GET['variant'] ?? 'original')));

        return in_array($variant, self::ALLOWED_VARIANTS, true) ? $variant : 'original';
    }

    private function fallbackVariant(string $variantKey): string
    {
        return match ($variantKey) {
            'thumb' => 'medium',
            'medium' => 'large',
            default => 'original',
        };
    }

    private function findMedia(TenantContext $tenant, string $mediaUuid, bool $requirePublishedArtwork): ?array
    {
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

        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function findVariant(int $mediaAssetId, string $variantKey): ?array
    {
        if (!$this->tableExists('media_asset_variants')) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
               FROM media_asset_variants
              WHERE media_asset_id = :media_asset_id
                AND variant_key = :variant_key
              LIMIT 1"
        );
        $stmt->execute([
            'media_asset_id' => $mediaAssetId,
            'variant_key' => $variantKey,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function originalVariant(array $media): array
    {
        return [
            'variant_key' => 'original',
            'storage_path' => (string) $media['storage_path'],
            'mime_type' => (string) ($media['mime_type'] ?: 'application/octet-stream'),
        ];
    }

    private function isSelectedBackground(TenantContext $tenant, string $mediaUuid): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT setting_value
                   FROM tenant_settings
                  WHERE tenant_id = :tenant_id
                    AND setting_key = 'background_media_uuid'
                  LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId]);

            return hash_equals((string) ($stmt->fetchColumn() ?: ''), $mediaUuid);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Returns true when media is assigned to a tenant presentation surface.
     *
     * Presentation media must remain visually clean and is excluded from
     * watermark rendering regardless of which URL helper served it.
     */
    private function isSelectedPresentationMedia(
        TenantContext $tenant,
        string $mediaUuid,
    ): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                   FROM tenant_settings
                  WHERE tenant_id = :tenant_id
                    AND setting_key IN (
                        'background_media_uuid',
                        'menu_media_uuid',
                        'topbar_media_uuid',
                        'artwork_card_media_uuid'
                    )
                    AND setting_value = :media_uuid"
            );
            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'media_uuid' => $mediaUuid,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name");
            $stmt->execute(['table_name' => $table]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}

// End of file.
