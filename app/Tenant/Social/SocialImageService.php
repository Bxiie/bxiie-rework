<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use RuntimeException;

/**
 * Creates non-destructive JPEG derivatives suitable for Instagram publishing.
 */
final class SocialImageService
{
    public function __construct(private readonly string $root)
    {
    }

    /** @return array{path:string,mime_type:string,width:int,height:int} */
    public function createDerivative(int $tenantId, int $postId, int $itemId, array $media, string $cropMode, array $crop = []): array
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('PHP GD is required for Instagram publishing derivatives.');
        }

        $sourcePath = $this->root . '/' . ltrim((string) ($media['storage_path'] ?? ''), '/');
        if (!is_file($sourcePath)) {
            throw new RuntimeException('Source image file is missing.');
        }

        $bytes = file_get_contents($sourcePath);
        $source = $bytes !== false ? @imagecreatefromstring($bytes) : false;
        if (!$source) {
            throw new RuntimeException('Source image format could not be decoded for Instagram publishing.');
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth < 1 || $sourceHeight < 1) {
                throw new RuntimeException('Source image has invalid dimensions.');
            }

            [$ratioWidth, $ratioHeight] = match ($cropMode) {
                'square' => [1, 1],
                'portrait' => [4, 5],
                'landscape' => [191, 100],
                default => [$sourceWidth, $sourceHeight],
            };

            $targetRatio = $ratioWidth / $ratioHeight;
            $sourceRatio = $sourceWidth / $sourceHeight;
            $cropWidth = $sourceWidth;
            $cropHeight = $sourceHeight;
            if ($cropMode !== 'original') {
                if ($sourceRatio > $targetRatio) {
                    $cropWidth = (int) round($sourceHeight * $targetRatio);
                } else {
                    $cropHeight = (int) round($sourceWidth / $targetRatio);
                }
            }

            $xPercent = max(0.0, min(1.0, (float) ($crop['x'] ?? 0.5)));
            $yPercent = max(0.0, min(1.0, (float) ($crop['y'] ?? 0.5)));
            $maxX = max(0, $sourceWidth - $cropWidth);
            $maxY = max(0, $sourceHeight - $cropHeight);
            $srcX = (int) round($maxX * $xPercent);
            $srcY = (int) round($maxY * $yPercent);

            $maxLongEdge = 1440;
            $scale = min(1.0, $maxLongEdge / max($cropWidth, $cropHeight));
            $targetWidth = max(1, (int) round($cropWidth * $scale));
            $targetHeight = max(1, (int) round($cropHeight * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$target) {
                throw new RuntimeException('Unable to allocate Instagram publishing image.');
            }

            try {
                $white = imagecolorallocate($target, 255, 255, 255);
                imagefill($target, 0, 0, $white);
                imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);

                $relativeDir = 'storage/social/instagram/' . $tenantId . '/' . $postId;
                $absoluteDir = $this->root . '/' . $relativeDir;
                if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
                    throw new RuntimeException('Unable to create Instagram derivative directory.');
                }
                $relativePath = $relativeDir . '/item-' . $itemId . '.jpg';
                $absolutePath = $this->root . '/' . $relativePath;
                if (!imagejpeg($target, $absolutePath, 90)) {
                    throw new RuntimeException('Unable to write Instagram JPEG derivative.');
                }
                @chmod($absolutePath, 0640);

                return ['path' => $relativePath, 'mime_type' => 'image/jpeg', 'width' => $targetWidth, 'height' => $targetHeight];
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }
}

// End of file.
