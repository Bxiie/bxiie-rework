<?php

declare(strict_types=1);

namespace App\Tenant\Artwork;

use App\Platform\Tenancy\TenantContext;
use PDO;
use RuntimeException;

/**
 * Stores tenant artwork uploads as media_assets and artworks records.
 */
final class ArtworkUploadService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function store(TenantContext $tenant, array $file, array $metadata): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Artwork upload failed.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $mime = mime_content_type($tmp) ?: 'application/octet-stream';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Artwork must be JPEG, PNG, WebP, or GIF.');
        }

        $title = $this->cleanText((string) ($metadata['title'] ?? ''));
        if ($title === '') {
            $title = 'Untitled artwork';
        }

        $artworkDate = $this->cleanText((string) ($metadata['artwork_date'] ?? ''));
        $medium = $this->cleanText((string) ($metadata['medium'] ?? ''));
        $notes = $this->cleanText((string) ($metadata['notes'] ?? ''));
        $saleStatus = $this->normalizeSaleStatus((string) ($metadata['sale_status'] ?? 'nfs'));
        $price = $this->cleanText((string) ($metadata['price'] ?? ''));

        if ($saleStatus === 'nfs') {
            $price = '';
        }

        $root = dirname(__DIR__, 3);
        $relativeDir = 'storage/uploads/artwork/' . $tenant->slug;
        $absoluteDir = $root . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Could not create artwork upload directory.');
        }

        $sha256 = hash_file('sha256', $tmp);
        $filename = $sha256 . '.' . $extensions[$mime];
        $relativePath = $relativeDir . '/' . $filename;
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!is_file($absolutePath) && !move_uploaded_file($tmp, $absolutePath)) {
            throw new RuntimeException('Could not store artwork upload.');
        }

        $dimensions = @getimagesize($absolutePath);
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : null;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : null;
        $fileSize = filesize($absolutePath) ?: null;

        $this->pdo->beginTransaction();

        try {
            $mediaId = $this->createMediaAsset(
                tenantId: (int) $tenant->id,
                originalFilename: (string) ($file['name'] ?? $filename),
                storagePath: $relativePath,
                mimeType: $mime,
                fileSizeBytes: $fileSize !== null ? (int) $fileSize : null,
                width: $width !== 0 ? $width : null,
                height: $height !== 0 ? $height : null,
                title: $title,
                altText: $title,
                caption: $notes,
            );

            $artworkId = $this->createArtwork(
                tenantId: (int) $tenant->id,
                mediaId: $mediaId,
                title: $title,
                artworkDate: $artworkDate,
                medium: $medium,
                notes: $notes,
                saleStatus: $saleStatus,
                price: $price,
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return [
            'artwork_id' => $artworkId,
            'media_id' => $mediaId,
            'tenant_id' => (int) $tenant->id,
            'tenant_slug' => $tenant->slug,
            'title' => $title,
            'artwork_date' => $artworkDate,
            'medium' => $medium,
            'notes' => $notes,
            'sale_status' => $saleStatus,
            'price' => $price,
            'storage_path' => $relativePath,
            'sha256' => $sha256,
            'mime_type' => $mime,
            'uploaded_at' => date('c'),
        ];
    }

    private function createMediaAsset(
        int $tenantId,
        string $originalFilename,
        string $storagePath,
        string $mimeType,
        ?int $fileSizeBytes,
        ?int $width,
        ?int $height,
        string $title,
        string $altText,
        string $caption,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO media_assets (
                uuid,
                tenant_id,
                original_filename,
                storage_path,
                mime_type,
                file_size_bytes,
                width,
                height,
                alt_text,
                title,
                caption,
                is_private,
                created_at,
                updated_at
            ) VALUES (
                UUID(),
                :tenant_id,
                :original_filename,
                :storage_path,
                :mime_type,
                :file_size_bytes,
                :width,
                :height,
                :alt_text,
                :title,
                :caption,
                0,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'original_filename' => $originalFilename,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSizeBytes,
            'width' => $width,
            'height' => $height,
            'alt_text' => $altText,
            'title' => $title,
            'caption' => $caption !== '' ? $caption : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createArtwork(
        int $tenantId,
        int $mediaId,
        string $title,
        string $artworkDate,
        string $medium,
        string $notes,
        string $saleStatus,
        string $price,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO artworks (
                uuid,
                tenant_id,
                primary_media_id,
                title,
                slug,
                description,
                medium,
                year_created,
                status,
                sale_status,
                price,
                sort_order,
                created_at,
                updated_at
            ) VALUES (
                UUID(),
                :tenant_id,
                :primary_media_id,
                :title,
                :slug,
                :description,
                :medium,
                :year_created,
                'draft',
                :sale_status,
                :price,
                0,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'primary_media_id' => $mediaId,
            'title' => $title,
            'slug' => $this->uniqueArtworkSlug($tenantId, $title),
            'description' => $notes !== '' ? $notes : null,
            'medium' => $medium !== '' ? $medium : null,
            'year_created' => $artworkDate !== '' ? $artworkDate : null,
            'sale_status' => $saleStatus,
            'price' => $price !== '' ? $price : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function uniqueArtworkSlug(int $tenantId, string $title): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        if ($base === '') {
            $base = 'artwork';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->artworkSlugExists($tenantId, $slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function artworkSlugExists(int $tenantId, string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM artworks WHERE tenant_id = :tenant_id AND slug = :slug LIMIT 1');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'slug' => $slug,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function normalizeSaleStatus(string $saleStatus): string
    {
        return in_array($saleStatus, ['nfs', 'for_sale', 'sold'], true) ? $saleStatus : 'nfs';
    }

    private function cleanText(string $value): string
    {
        return trim(str_replace("\0", '', $value));
    }
}

// End of file.
