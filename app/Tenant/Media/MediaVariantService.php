<?php

// Generates and records tenant media variants used by public grids and detail pages.

declare(strict_types=1);

namespace App\Tenant\Media;

use PDO;
use RuntimeException;

final class MediaVariantService
{
    /** @var array<string,int> */
    private const VARIANT_LIMITS = [
        'thumb' => 480,
        'medium' => 1200,
        'large' => 2000,
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $projectRoot,
    ) {
    }

    /**
     * Generates best-effort variants and records the original file as a first-class variant.
     *
     * Unsupported image libraries or formats fall back to the original file. That keeps uploads
     * working while still letting the media controller make deterministic variant decisions.
     */
    public function createForMediaAsset(
        int $mediaAssetId,
        string $sourceRelativePath,
        string $mimeType,
        ?int $sourceWidth,
        ?int $sourceHeight,
        ?int $sourceBytes,
    ): void {
        $sourceAbsolutePath = $this->absolutePath($sourceRelativePath);

        if (!is_file($sourceAbsolutePath)) {
            throw new RuntimeException('Cannot create media variants because the source file is missing.');
        }

        $this->upsertVariant(
            mediaAssetId: $mediaAssetId,
            variantKey: 'original',
            storagePath: $sourceRelativePath,
            mimeType: $mimeType,
            width: $sourceWidth,
            height: $sourceHeight,
            fileSizeBytes: $sourceBytes,
        );

        $variantDirectory = dirname($sourceAbsolutePath) . '/variants';
        $variantRelativeDirectory = dirname($sourceRelativePath) . '/variants';

        if (!is_dir($variantDirectory) && !mkdir($variantDirectory, 0775, true) && !is_dir($variantDirectory)) {
            throw new RuntimeException('Could not create media variant directory.');
        }

        foreach (self::VARIANT_LIMITS as $variantKey => $maxDimension) {
            $variantRelativePath = $this->variantRelativePath($variantRelativeDirectory, $sourceRelativePath, $variantKey, $mimeType);
            $variantAbsolutePath = $this->absolutePath($variantRelativePath);

            $created = $this->createResizedImage(
                sourceAbsolutePath: $sourceAbsolutePath,
                destinationAbsolutePath: $variantAbsolutePath,
                mimeType: $mimeType,
                maxDimension: $maxDimension,
            );

            if (!$created) {
                $variantRelativePath = $sourceRelativePath;
                $variantAbsolutePath = $sourceAbsolutePath;
            }

            $dimensions = @getimagesize($variantAbsolutePath);
            $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : null;
            $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : null;
            $bytes = filesize($variantAbsolutePath) ?: null;

            $this->upsertVariant(
                mediaAssetId: $mediaAssetId,
                variantKey: $variantKey,
                storagePath: $variantRelativePath,
                mimeType: $mimeType,
                width: $width !== 0 ? $width : null,
                height: $height !== 0 ? $height : null,
                fileSizeBytes: $bytes !== null ? (int) $bytes : null,
            );
        }
    }

    /**
     * Returns false when the runtime cannot safely resize the file.
     */
    private function createResizedImage(
        string $sourceAbsolutePath,
        string $destinationAbsolutePath,
        string $mimeType,
        int $maxDimension,
    ): bool {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $dimensions = @getimagesize($sourceAbsolutePath);
        if (!is_array($dimensions)) {
            return false;
        }

        $sourceWidth = (int) ($dimensions[0] ?? 0);
        $sourceHeight = (int) ($dimensions[1] ?? 0);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return false;
        }

        $scale = min(1.0, $maxDimension / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        if (is_file($destinationAbsolutePath)) {
            return true;
        }

        $sourceImage = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourceAbsolutePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourceAbsolutePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourceAbsolutePath) : false,
            default => false,
        };

        if (!$sourceImage) {
            return false;
        }

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        $saved = match ($mimeType) {
            'image/jpeg' => imagejpeg($targetImage, $destinationAbsolutePath, 82),
            'image/png' => imagepng($targetImage, $destinationAbsolutePath, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($targetImage, $destinationAbsolutePath, 82) : false,
            default => false,
        };

        // PHP 8.5 releases GDImage memory automatically when references leave scope.
        return (bool) $saved;
    }

    private function variantRelativePath(
        string $variantRelativeDirectory,
        string $sourceRelativePath,
        string $variantKey,
        string $mimeType,
    ): string {
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => pathinfo($sourceRelativePath, PATHINFO_EXTENSION) ?: 'bin',
        };

        $baseName = pathinfo($sourceRelativePath, PATHINFO_FILENAME);

        return $variantRelativeDirectory . '/' . $baseName . '-' . $variantKey . '.' . $extension;
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->projectRoot, '/') . '/' . ltrim($relativePath, '/');
    }

    private function upsertVariant(
        int $mediaAssetId,
        string $variantKey,
        string $storagePath,
        string $mimeType,
        ?int $width,
        ?int $height,
        ?int $fileSizeBytes,
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO media_asset_variants (
                media_asset_id,
                variant_key,
                storage_path,
                mime_type,
                width,
                height,
                file_size_bytes,
                created_at,
                updated_at
            ) VALUES (
                :media_asset_id,
                :variant_key,
                :storage_path,
                :mime_type,
                :width,
                :height,
                :file_size_bytes,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                storage_path = VALUES(storage_path),
                mime_type = VALUES(mime_type),
                width = VALUES(width),
                height = VALUES(height),
                file_size_bytes = VALUES(file_size_bytes),
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'media_asset_id' => $mediaAssetId,
            'variant_key' => $variantKey,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
            'file_size_bytes' => $fileSizeBytes,
        ]);
    }
}

// End of file.
