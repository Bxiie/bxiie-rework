<?php
/**
 * Image derivative generation and optional watermarking.
 */

declare(strict_types=1);

namespace App\Services;

final class ImageService
{
    public function __construct(private array $config)
    {
    }

    public function processUpload(array $file, int $tenantId, bool $watermark): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Image upload failed.');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Only JPG, PNG, and WEBP images are supported.');
        }

        $base = $this->config['storage_path'] . '/uploads/' . $tenantId;
        $cache = $this->config['storage_path'] . '/cache/' . $tenantId;
        if (!is_dir($base)) {
            mkdir($base, 0775, true);
        }
        if (!is_dir($cache)) {
            mkdir($cache, 0775, true);
        }

        $id = bin2hex(random_bytes(12));
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $original = $base . '/' . $id . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $original);

        foreach ($this->config['image_sizes'] as $label => $maxWidth) {
            $this->makeDerivative($original, $cache . '/' . $id . '-' . $label . '.jpg', (int) $maxWidth, $watermark);
        }

        [$width, $height] = getimagesize($original) ?: [0, 0];
        return [
            'storage_key' => $id,
            'original_path' => $original,
            'mime_type' => $mime,
            'width' => $width,
            'height' => $height,
            'watermarked' => $watermark ? 1 : 0,
        ];
    }

    private function makeDerivative(string $source, string $target, int $maxWidth, bool $watermark): void
    {
        [$width, $height] = getimagesize($source);
        $ratio = min(1, $maxWidth / max(1, $width));
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        $src = imagecreatefromstring(file_get_contents($source));
        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        if ($watermark) {
            $text = $this->config['watermark_text'];
            $color = imagecolorallocatealpha($dst, 255, 255, 255, 55);
            imagestring($dst, 5, max(12, $newWidth - 12 - strlen($text) * 10), max(12, $newHeight - 32), $text, $color);
        }

        imagejpeg($dst, $target, 86);
        imagedestroy($src);
        imagedestroy($dst);
    }
}

// End of file.
