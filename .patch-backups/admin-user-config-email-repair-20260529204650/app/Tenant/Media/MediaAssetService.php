<?php

declare(strict_types=1);

namespace App\Tenant\Media;

use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantStoragePaths;
use App\Support\Storage\StorageInterface;
use App\Support\Uuid;

/**
 * Coordinates tenant media storage and database persistence.
 */
final class MediaAssetService
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly MediaAssetRepository $repository,
    ) {
    }

    public function storeOriginal(
        TenantContext $tenant,
        string $originalFilename,
        string $contents,
        ?string $mimeType = null,
        ?int $width = null,
        ?int $height = null,
        ?string $title = null,
        ?string $altText = null,
        ?string $caption = null,
    ): int {
        $paths = new TenantStoragePaths($tenant);
        $uuid = Uuid::v4();

        $safeFilename = $uuid . '-' . basename($originalFilename);
        $storagePath = $paths->originalsPath($safeFilename);

        $this->storage->put($storagePath, $contents);

        return $this->repository->create(
            tenant: $tenant,
            uuid: $uuid,
            originalFilename: $originalFilename,
            storagePath: $storagePath,
            mimeType: $mimeType,
            fileSizeBytes: strlen($contents),
            width: $width,
            height: $height,
            title: $title,
            altText: $altText,
            caption: $caption,
        );
    }
}

// End of file.
