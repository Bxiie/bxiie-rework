<?php

declare(strict_types=1);

namespace App\Tenant\Media;

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

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
        $text = $this->watermarkText($tenant);

        if ($width < 1 || $height < 1 || $text === '') {
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
