<?php

// Produces a downloadable copy of an artwork's primary image: the original
// file as-is, or resized/reformatted/watermarked on request.

declare(strict_types=1);

namespace App\Tenant\Media;

use App\Platform\Tenancy\TenantContext;
use PDO;
use RuntimeException;

final class ArtworkExportService
{
    /** @var array<string,string> */
    private const FORMAT_MIME = [
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /** @var array<string,string> */
    private const MIME_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly WatermarkService $watermark,
        private readonly string $projectRoot,
    ) {
    }

    /** @return list<string> */
    public static function supportedFormats(): array
    {
        return ['original', 'jpeg', 'png', 'webp'];
    }

    /**
     * Resize presets keyed by max dimension in pixels; 0 means no resize.
     * Matches the limits MediaVariantService already uses for generated
     * variants, so exported sizes line up with what tenants already see.
     *
     * @return array<int,string>
     */
    public static function resizeOptions(): array
    {
        return [
            0 => 'Original size',
            2000 => 'Large (max 2000px)',
            1200 => 'Medium (max 1200px)',
            480 => 'Small (max 480px)',
        ];
    }

    /**
     * @return array{bytes:string,mime:string,filename:string}
     */
    public function export(
        TenantContext $tenant,
        int $artworkId,
        string $format,
        ?int $maxDimension,
        bool $applyWatermark,
    ): array {
        $media = $this->primaryMedia($tenant, $artworkId);
        if ($media === null) {
            throw new RuntimeException('This artwork has no exportable image.');
        }

        $sourceMime = strtolower((string) ($media['mime_type'] ?? ''));
        if (!isset(self::MIME_EXTENSION[$sourceMime])) {
            throw new RuntimeException('This image type cannot be exported.');
        }

        $sourcePath = $this->absolutePath((string) $media['storage_path']);
        if (!is_file($sourcePath)) {
            throw new RuntimeException('The source image file is missing from storage.');
        }

        $targetMime = $format === 'original' ? $sourceMime : (self::FORMAT_MIME[$format] ?? null);
        if ($targetMime === null) {
            throw new RuntimeException('Unsupported export format.');
        }

        // Watermarking (if requested) always runs first, directly against the
        // stored original, so a later resize/convert pass works from pixels
        // that already include it rather than trying to re-stamp a copy.
        $watermarkedBytes = $applyWatermark
            ? ($this->watermark->render($tenant, $sourcePath, $sourceMime) ?? (string) file_get_contents($sourcePath))
            : null;

        // Only invoke the lossy GD re-encode path when a resize will actually
        // change pixels (never upscaling) or the format truly differs.
        // Otherwise a "Large" preset picked on an already-small image would
        // silently re-encode and degrade it for no visual change.
        $dimensions = $watermarkedBytes !== null
            ? @getimagesizefromstring($watermarkedBytes)
            : @getimagesize($sourcePath);
        $needsResize = $maxDimension !== null
            && $maxDimension > 0
            && is_array($dimensions)
            && max((int) $dimensions[0], (int) $dimensions[1]) > $maxDimension;
        $needsConvert = $targetMime !== $sourceMime;

        if (!$needsResize && !$needsConvert) {
            $bytes = $watermarkedBytes ?? (string) file_get_contents($sourcePath);
        } else {
            $bytes = $this->renderResizedAndConverted(
                $watermarkedBytes ?? $sourcePath,
                $watermarkedBytes !== null,
                $sourceMime,
                $targetMime,
                $needsResize ? $maxDimension : null,
            );
        }

        if ($bytes === null || $bytes === '') {
            throw new RuntimeException('The image could not be processed for export.');
        }

        return [
            'bytes' => $bytes,
            'mime' => $targetMime,
            'filename' => $this->exportFilename((string) ($media['original_filename'] ?? 'artwork'), $targetMime, $applyWatermark),
        ];
    }

    /** @return array<string,mixed>|null */
    private function primaryMedia(TenantContext $tenant, int $artworkId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.storage_path, m.mime_type, m.original_filename
             FROM artworks a
             JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id
             WHERE a.id = :artwork_id
               AND a.tenant_id = :tenant_id
             LIMIT 1"
        );
        $stmt->execute(['artwork_id' => $artworkId, 'tenant_id' => $tenant->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->projectRoot, '/') . '/' . ltrim($relativePath, '/');
    }

    /**
     * Loads from either a file path or in-memory bytes, resizes to fit within
     * maxDimension when given (never upscaling, same rule as generated
     * variants), and encodes to targetMime.
     */
    private function renderResizedAndConverted(
        string $source,
        bool $sourceIsBytes,
        string $sourceMime,
        string $targetMime,
        ?int $maxDimension,
    ): ?string {
        if (!extension_loaded('gd')) {
            return null;
        }

        $image = $sourceIsBytes
            ? @imagecreatefromstring($source)
            : match ($sourceMime) {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
                default => false,
            };

        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($maxDimension !== null && $maxDimension > 0 && max($width, $height) > $maxDimension) {
            $scale = $maxDimension / max($width, $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($targetMime === 'image/png' || $targetMime === 'image/webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        $encoded = match ($targetMime) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 90) : false,
            default => false,
        };
        $bytes = ob_get_clean();
        // PHP 8.5 releases GDImage memory automatically when references leave scope.
        imagedestroy($image);

        return $encoded && is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    private function exportFilename(string $originalFilename, string $mime, bool $watermarked): string
    {
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $base = $base !== '' ? $base : 'artwork';
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', $base) ?: 'artwork';
        $extension = self::MIME_EXTENSION[$mime] ?? 'bin';
        $suffix = $watermarked ? '-watermarked' : '';

        return $safeBase . $suffix . '.' . $extension;
    }
}

// End of file.
