<?php

// Replaces (or sets, if missing) an artwork's primary image from an uploaded
// file. Follows the same pattern as MediaRotationService: a new media_assets
// row and variants are created and artworks.primary_media_id is repointed at
// it, rather than overwriting files in place, so old variant URLs/caches
// already issued for the previous image keep serving the previous bytes.

declare(strict_types=1);

namespace App\Tenant\Media;

use App\Platform\Tenancy\TenantContext;
use PDO;
use RuntimeException;

final class ArtworkImageReplaceService
{
    /** @var array<string,string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param array<string,mixed> $file one entry from $_FILES
     */
    public function replaceArtworkPrimary(TenantContext $tenant, int $artworkId, array $file): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose an image file to upload.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        return $this->replaceFromTemporaryFile($tenant, $artworkId, $tmp, (string) ($file['name'] ?? ''));
    }

    /**
     * Does the actual mime validation, storage, and database work from a
     * plain temporary file path. Split out from replaceArtworkPrimary() so
     * it can be exercised directly (e.g. in tests) without PHP's upload
     * machinery, which only recognizes files it moved itself in the current
     * request.
     */
    private function replaceFromTemporaryFile(TenantContext $tenant, int $artworkId, string $tmp, string $originalFilename): int
    {
        $mime = mime_content_type($tmp) ?: 'application/octet-stream';
        if (!isset(self::EXTENSIONS[$mime])) {
            throw new RuntimeException('Artwork image must be JPEG, PNG, WebP, or GIF.');
        }

        $artwork = $this->findArtwork($tenant, $artworkId);
        if ($artwork === null) {
            throw new RuntimeException('Artwork not found.');
        }

        $existingMedia = $artwork['primary_media_id'] !== null
            ? $this->findMedia($tenant, (int) $artwork['primary_media_id'])
            : null;

        $relativeDir = 'storage/uploads/artwork/' . $tenant->slug;
        $absoluteDir = $this->absolute($relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Could not create artwork upload directory.');
        }

        $filename = hash_file('sha256', $tmp) . '.' . self::EXTENSIONS[$mime];
        $relativePath = $relativeDir . '/' . $filename;
        $absolutePath = $this->absolute($relativePath);
        $createdFile = !is_file($absolutePath);
        if ($createdFile && !copy($tmp, $absolutePath)) {
            throw new RuntimeException('Could not store the uploaded image.');
        }

        $dimensions = @getimagesize($absolutePath);
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : null;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : null;
        $fileSize = filesize($absolutePath) ?: null;

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO media_assets (
                    uuid, tenant_id, original_filename, storage_path, mime_type,
                    file_size_bytes, width, height, alt_text, title, caption, credit,
                    is_private, created_at, updated_at
                ) VALUES (
                    UUID(), :tenant_id, :original_filename, :storage_path, :mime_type,
                    :file_size_bytes, :width, :height, :alt_text, :title, :caption, :credit,
                    :is_private, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )'
            );
            $insert->execute([
                'tenant_id' => $tenant->tenantId,
                'original_filename' => $originalFilename !== '' ? $originalFilename : $filename,
                'storage_path' => $relativePath,
                'mime_type' => $mime,
                'file_size_bytes' => $fileSize !== null ? (int) $fileSize : null,
                'width' => $width !== 0 ? $width : null,
                'height' => $height !== 0 ? $height : null,
                'alt_text' => $existingMedia['alt_text'] ?? (string) $artwork['title'],
                'title' => $existingMedia['title'] ?? (string) $artwork['title'],
                'caption' => $existingMedia['caption'] ?? null,
                'credit' => $existingMedia['credit'] ?? null,
                'is_private' => (int) ($existingMedia['is_private'] ?? 0),
            ]);
            $mediaId = (int) $this->pdo->lastInsertId();

            (new MediaVariantService($this->pdo, $this->projectRoot))->createForMediaAsset(
                mediaAssetId: $mediaId,
                sourceRelativePath: $relativePath,
                mimeType: $mime,
                sourceWidth: $width !== 0 ? $width : null,
                sourceHeight: $height !== 0 ? $height : null,
                sourceBytes: $fileSize !== null ? (int) $fileSize : null,
            );

            $update = $this->pdo->prepare(
                'UPDATE artworks SET primary_media_id = :media_id, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :artwork_id AND tenant_id = :tenant_id'
            );
            $update->execute(['media_id' => $mediaId, 'artwork_id' => $artworkId, 'tenant_id' => $tenant->tenantId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The artwork image could not be updated.');
            }

            $this->pdo->commit();

            return $mediaId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($createdFile) {
                @unlink($absolutePath);
            }

            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    private function findArtwork(TenantContext $tenant, int $artworkId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, primary_media_id
             FROM artworks
             WHERE id = :artwork_id AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['artwork_id' => $artworkId, 'tenant_id' => $tenant->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findMedia(TenantContext $tenant, int $mediaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT alt_text, title, caption, credit, is_private
             FROM media_assets
             WHERE id = :media_id AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['media_id' => $mediaId, 'tenant_id' => $tenant->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function absolute(string $relativePath): string
    {
        return rtrim($this->projectRoot, '/') . '/' . ltrim($relativePath, '/');
    }
}

// End of file.
