<?php

declare(strict_types=1);

namespace App\Tenant\Media;

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;

/**
 * Applies an opt-in watermark to public medium, large, and original images.
 *
 * Thumbnail and admin responses remain unwatermarked. Stored source files are
 * never modified; the watermark is rendered into the public response.
 */
final class WatermarkService
{
    private const FINGERPRINT_KEYS = [
        'watermark_enabled',
        'watermark_media_uuid',
        'watermark_mode',
        'watermark_format',
        'watermark_text',
        'watermark_position',
        'watermark_opacity',
        'watermark_size',
        'watermark_color',
        'artist_name',
        'site_title',
    ];

    public function __construct(
        private readonly TenantSettingsRepository $settings,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function enabled(TenantContext $tenant): bool
    {
        return $this->settings->get($tenant, 'watermark_enabled', '0') === '1';
    }

    /** Returns a stable settings fingerprint for HTTP cache invalidation. */
    public function fingerprint(TenantContext $tenant): string
    {
        $values = [];

        foreach (self::FINGERPRINT_KEYS as $key) {
            $values[$key] = (string) $this->settings->get($tenant, $key, '');
        }

        return sha1(
            json_encode(
                $values,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ) ?: ''
        );
    }

    /** Returns encoded watermarked bytes, or null when rendering is unavailable. */
    public function render(
        TenantContext $tenant,
        string $path,
        string $mimeType,
    ): ?string {
        if (!$this->enabled($tenant) || !extension_loaded('gd')) {
            return null;
        }

        $image = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg')
                ? @imagecreatefromjpeg($path)
                : false,
            'image/png' => function_exists('imagecreatefrompng')
                ? @imagecreatefrompng($path)
                : false,
            'image/webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($path)
                : false,
            default => false,
        };

        if ($image === false) {
            return null;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $mode = $this->watermarkMode($tenant);
        $text = in_array($mode, ['text', 'both'], true)
            ? $this->watermarkText($tenant)
            : '';
        $watermarkImage = in_array($mode, ['image', 'both'], true)
            ? $this->watermarkImage($tenant)
            : null;

        if (
            $width < 1
            || $height < 1
            || ($text === '' && $watermarkImage === null)
        ) {
            if ($watermarkImage instanceof \GdImage) {
                imagedestroy($watermarkImage);
            }
            imagedestroy($image);
            return null;
        }

        $sizeChoice = max(
            1,
            min(
                5,
                (int) $this->settings->get(
                    $tenant,
                    'watermark_size',
                    '3',
                )
            )
        );
        $opacity = max(
            0.05,
            min(
                1.0,
                (float) $this->settings->get(
                    $tenant,
                    'watermark_opacity',
                    '0.55',
                )
            )
        );
        $isBlack = strtolower(
            (string) $this->settings->get(
                $tenant,
                'watermark_color',
                'white',
            )
        ) === 'black';
        $alpha = (int) round(127 * (1.0 - $opacity));

        $foreground = imagecolorallocatealpha(
            $image,
            $isBlack ? 0 : 255,
            $isBlack ? 0 : 255,
            $isBlack ? 0 : 255,
            $alpha,
        );
        $shadow = imagecolorallocatealpha(
            $image,
            $isBlack ? 255 : 0,
            $isBlack ? 255 : 0,
            $isBlack ? 255 : 0,
            min(120, $alpha + 38),
        );

        $padding = max(
            10,
            (int) round(min($width, $height) * 0.025),
        );
        $position = (string) $this->settings->get(
            $tenant,
            'watermark_position',
            'bottom-right',
        );
        if ($watermarkImage instanceof \GdImage) {
            $this->renderImageWatermark(
                $image,
                $watermarkImage,
                $position,
                $padding,
                $opacity,
                $sizeChoice,
            );
            imagedestroy($watermarkImage);
        }

        if ($text !== '') {
            $fontPath = $this->fontPath();

            if (
                $fontPath !== null
                && function_exists('imagettftext')
                && function_exists('imagettfbbox')
            ) {
                $fontSize = max(
                    12,
                    (int) round(
                        min($width, $height)
                        * (0.018 + ($sizeChoice * 0.006))
                    )
                );
                $box = imagettfbbox($fontSize, 0, $fontPath, $text);

                if (is_array($box)) {
                    $textWidth = abs($box[2] - $box[0]);
                    $textHeight = abs($box[7] - $box[1]);
                    [$x, $top] = $this->position(
                        $position,
                        $width,
                        $height,
                        $textWidth,
                        $textHeight,
                        $padding,
                    );
                    $baseline = $top + $textHeight;
                    $shadowOffset = max(
                        1,
                        (int) round($fontSize * 0.08),
                    );

                    imagettftext(
                        $image,
                        $fontSize,
                        0,
                        $x + $shadowOffset,
                        $baseline + $shadowOffset,
                        $shadow,
                        $fontPath,
                        $text,
                    );
                    imagettftext(
                        $image,
                        $fontSize,
                        0,
                        $x,
                        $baseline,
                        $foreground,
                        $fontPath,
                        $text,
                    );
                } else {
                    $this->renderBuiltinFont(
                        $image,
                        $text,
                        $position,
                        $padding,
                        $foreground,
                        $shadow,
                    );
                }
            } else {
                $this->renderBuiltinFont(
                    $image,
                    $text,
                    $position,
                    $padding,
                    $foreground,
                    $shadow,
                );
            }
        }

        ob_start();

        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 90),
            default => false,
        };

        $bytes = ob_get_clean();
        imagedestroy($image);

        return $encoded && is_string($bytes) && $bytes !== ''
            ? $bytes
            : null;
    }

    private function watermarkMode(TenantContext $tenant): string
    {
        $mode = strtolower(trim((string) $this->settings->get($tenant, 'watermark_mode', 'text')));
        return in_array($mode, ['text', 'image', 'both'], true) ? $mode : 'text';
    }

    private function watermarkImage(TenantContext $tenant): ?\GdImage
    {
        if ($this->pdo === null) {
            return null;
        }

        $uuid = strtolower(trim((string) $this->settings->get($tenant, 'watermark_media_uuid', '')));
        if (!preg_match('/^[a-f0-9-]{36}$/', $uuid)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.storage_path, m.mime_type
             FROM media_assets m
             INNER JOIN artworks a ON a.primary_media_id = m.id AND a.tenant_id = m.tenant_id
             INNER JOIN artwork_type_assignments ata ON ata.artwork_id = a.id
             INNER JOIN artwork_types atype ON atype.id = ata.type_id AND atype.code = 'site_images'
             WHERE m.tenant_id = :tenant_id
               AND m.uuid = :media_uuid
               AND m.is_private = 0
               AND COALESCE(a.status, '') <> 'archived'
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'media_uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $absolute = dirname(__DIR__, 3) . '/' . ltrim((string) $row['storage_path'], '/');
        if (!is_file($absolute)) {
            return null;
        }

        $mimeType = strtolower((string) ($row['mime_type'] ?? ''));
        $image = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absolute) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absolute) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    private function renderImageWatermark(
        \GdImage $target,
        \GdImage $watermark,
        string $position,
        int $padding,
        float $opacity,
        int $sizeChoice,
    ): void {
        $sourceWidth = imagesx($watermark);
        $sourceHeight = imagesy($watermark);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return;
        }

        $targetWidth = imagesx($target);
        $targetHeight = imagesy($target);
        $scale = 0.08 + ($sizeChoice * 0.035);
        $ratio = min(
            max(24, (int) round($targetWidth * $scale)) / $sourceWidth,
            max(24, (int) round($targetHeight * $scale)) / $sourceHeight,
            1.0,
        );
        $renderWidth = max(1, (int) round($sourceWidth * $ratio));
        $renderHeight = max(1, (int) round($sourceHeight * $ratio));
        [$x, $y] = $this->position($position, $targetWidth, $targetHeight, $renderWidth, $renderHeight, $padding);

        $scaled = imagecreatetruecolor($renderWidth, $renderHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefill($scaled, 0, 0, $transparent);
        imagecopyresampled($scaled, $watermark, 0, 0, 0, 0, $renderWidth, $renderHeight, $sourceWidth, $sourceHeight);
        $opacity = max(0.0, min(1.0, $opacity));
        for ($pixelY = 0; $pixelY < $renderHeight; $pixelY++) {
            for ($pixelX = 0; $pixelX < $renderWidth; $pixelX++) {
                $rgba = imagecolorat($scaled, $pixelX, $pixelY);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha >= 127) {
                    continue;
                }

                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $adjustedAlpha = 127 - (int) round(
                    (127 - $alpha) * $opacity
                );
                $color = imagecolorallocatealpha(
                    $scaled,
                    $red,
                    $green,
                    $blue,
                    max(0, min(127, $adjustedAlpha)),
                );
                imagesetpixel($scaled, $pixelX, $pixelY, $color);
            }
        }

        imagealphablending($target, true);
        imagecopy(
            $target,
            $scaled,
            $x,
            $y,
            0,
            0,
            $renderWidth,
            $renderHeight,
        );
        imagedestroy($scaled);
    }

    private function watermarkText(TenantContext $tenant): string
    {
        $format = (string) $this->settings->get(
            $tenant,
            'watermark_format',
            'copyright_artist',
        );
        $artist = trim(
            (string) $this->settings->get(
                $tenant,
                'artist_name',
                $tenant->name,
            )
        );
        $siteTitle = trim(
            (string) $this->settings->get(
                $tenant,
                'site_title',
                $tenant->name,
            )
        );
        $custom = trim(
            (string) $this->settings->get(
                $tenant,
                'watermark_text',
                '',
            )
        );

        return match ($format) {
            'artist' => $artist,
            'custom' => $custom !== '' ? $custom : $artist,
            'site' => $siteTitle !== '' ? $siteTitle : $tenant->name,
            default => '© ' . date('Y') . ' ' . $artist,
        };
    }

    /** Uses common operating-system font locations without shipping fonts. */
    private function fontPath(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Uses GD's built-in font only when TrueType support is unavailable. */
    private function renderBuiltinFont(
        \GdImage $image,
        string $text,
        string $position,
        int $padding,
        int $foreground,
        int $shadow,
    ): void {
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        [$x, $y] = $this->position(
            $position,
            imagesx($image),
            imagesy($image),
            $textWidth,
            $textHeight,
            $padding,
        );

        imagestring($image, $font, $x + 1, $y + 1, $text, $shadow);
        imagestring($image, $font, $x, $y, $text, $foreground);
    }

    /**
     * Returns the top-left coordinate for the configured position.
     *
     * @return array{0:int,1:int}
     */
    private function position(
        string $position,
        int $width,
        int $height,
        int $textWidth,
        int $textHeight,
        int $padding,
    ): array {
        $centered = $position === 'center';
        $left = str_contains($position, 'left');
        $top = str_contains($position, 'top');

        $x = $centered
            ? max($padding, (int) round(($width - $textWidth) / 2))
            : (
                $left
                    ? $padding
                    : max($padding, $width - $textWidth - $padding)
            );
        $y = $centered
            ? max($padding, (int) round(($height - $textHeight) / 2))
            : (
                $top
                    ? $padding
                    : max($padding, $height - $textHeight - $padding)
            );

        return [$x, $y];
    }
}

// End of file.
