<?php

declare(strict_types=1);

namespace App\Tenant\Media;

use PDO;
use RuntimeException;

final class MediaRotationService
{
    public function __construct(private readonly PDO $pdo, private readonly string $projectRoot)
    {
    }

    public function rotateArtworkPrimary(int $tenantId, int $artworkId, string $direction): int
    {
        if (!in_array($direction, ['left', 'right'], true)) {
            throw new RuntimeException('Choose rotate left or rotate right.');
        }
        if (!function_exists('imagerotate')) {
            throw new RuntimeException('Image rotation is not available on this server.');
        }

        $stmt = $this->pdo->prepare('SELECT m.* FROM artworks a JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id WHERE a.id = :artwork_id AND a.tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['artwork_id' => $artworkId, 'tenant_id' => $tenantId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$media) throw new RuntimeException('This artwork does not have a primary image.');

        $mime = (string) ($media['mime_type'] ?? '');
        $sourcePath = $this->absolute((string) $media['storage_path']);
        if (!is_file($sourcePath)) throw new RuntimeException('The primary image file is missing.');
        $source = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if (!$source) throw new RuntimeException('Only JPEG, PNG, and WebP artwork images can be rotated.');

        $rotated = imagerotate($source, $direction === 'left' ? 90 : -90, 0);
        if (!$rotated) throw new RuntimeException('The image could not be rotated.');
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
        }

        $directory = dirname($sourcePath);
        $temporary = tempnam($directory, '.rotate-');
        if ($temporary === false) throw new RuntimeException('Could not prepare the rotated image.');
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($rotated, $temporary, 92),
            'image/png' => imagepng($rotated, $temporary, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($rotated, $temporary, 90) : false,
            default => false,
        };
        if (!$saved) {
            @unlink($temporary);
            throw new RuntimeException('Could not save the rotated image.');
        }

        $extension = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' };
        $filename = hash_file('sha256', $temporary) . '.' . $extension;
        $relativePath = trim(dirname((string) $media['storage_path']), './') . '/' . $filename;
        $targetPath = $this->absolute($relativePath);
        $createdFile = !is_file($targetPath);
        if ($createdFile && !rename($temporary, $targetPath)) {
            @unlink($temporary);
            throw new RuntimeException('Could not store the rotated image.');
        }
        if (!$createdFile) @unlink($temporary);

        $dimensions = getimagesize($targetPath) ?: [];
        $bytes = filesize($targetPath) ?: null;
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare('INSERT INTO media_assets (uuid, tenant_id, original_filename, storage_path, mime_type, file_size_bytes, width, height, alt_text, title, caption, credit, is_private, created_at, updated_at) VALUES (UUID(), :tenant_id, :original_filename, :storage_path, :mime_type, :file_size_bytes, :width, :height, :alt_text, :title, :caption, :credit, :is_private, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            $insert->execute([
                'tenant_id' => $tenantId, 'original_filename' => (string) $media['original_filename'],
                'storage_path' => $relativePath, 'mime_type' => $mime, 'file_size_bytes' => $bytes,
                'width' => (int) ($dimensions[0] ?? 0) ?: null, 'height' => (int) ($dimensions[1] ?? 0) ?: null,
                'alt_text' => $media['alt_text'], 'title' => $media['title'], 'caption' => $media['caption'],
                'credit' => $media['credit'], 'is_private' => (int) ($media['is_private'] ?? 0),
            ]);
            $mediaId = (int) $this->pdo->lastInsertId();
            (new MediaVariantService($this->pdo, $this->projectRoot))->createForMediaAsset($mediaId, $relativePath, $mime, (int) ($dimensions[0] ?? 0) ?: null, (int) ($dimensions[1] ?? 0) ?: null, $bytes !== null ? (int) $bytes : null);
            $update = $this->pdo->prepare('UPDATE artworks SET primary_media_id = :media_id, updated_at = CURRENT_TIMESTAMP WHERE id = :artwork_id AND tenant_id = :tenant_id');
            $update->execute(['media_id' => $mediaId, 'artwork_id' => $artworkId, 'tenant_id' => $tenantId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('The artwork image could not be updated.');
            $this->pdo->commit();
            return $mediaId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($createdFile) @unlink($targetPath);
            throw $e;
        }
    }

    private function absolute(string $relativePath): string
    {
        return rtrim($this->projectRoot, '/') . '/' . ltrim($relativePath, '/');
    }
}

// End of file.
