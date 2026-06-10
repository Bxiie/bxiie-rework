<?php

declare(strict_types=1);

namespace App\Tenant\Media;

use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Handles database persistence for tenant media assets.
 */
final class MediaAssetRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        TenantContext $tenant,
        string $uuid,
        string $originalFilename,
        string $storagePath,
        ?string $mimeType,
        ?int $fileSizeBytes,
        ?int $width,
        ?int $height,
        ?string $title = null,
        ?string $altText = null,
        ?string $caption = null,
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
                title,
                alt_text,
                caption
            ) VALUES (
                :uuid,
                :tenant_id,
                :original_filename,
                :storage_path,
                :mime_type,
                :file_size_bytes,
                :width,
                :height,
                :title,
                :alt_text,
                :caption
            )"
        );

        $stmt->execute([
            'uuid' => $uuid,
            'tenant_id' => $tenant->tenantId,
            'original_filename' => $originalFilename,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSizeBytes,
            'width' => $width,
            'height' => $height,
            'title' => $title,
            'alt_text' => $altText,
            'caption' => $caption,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}

// End of file.
