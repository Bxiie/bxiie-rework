<?php

declare(strict_types=1);

namespace App\Tenant\Media;

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

/** Applies an opt-in tenant watermark to public image responses. */
final class WatermarkService
{
    public function __construct(private readonly TenantSettingsRepository $settings)
    {
    }

    public function enabled(TenantContext $tenant): bool
    {
        return $this->settings->get($tenant, 'watermark_enabled', '0') === '1';
    }

    /** Returns encoded image bytes, or null when GD/format support is unavailable. */
    public function render(TenantContext $tenant, string $path, string $mimeType): ?string
    {
        if (!$this->enabled($tenant) || !function_exists('imagecreatetruecolor')) return null;
        $image = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$image) return null;

        $width = imagesx($image); $height = imagesy($image);
        $format = $this->settings->get($tenant, 'watermark_format', 'copyright_artist');
        $artist = trim($this->settings->get($tenant, 'artist_name', $tenant->name));
        $custom = trim($this->settings->get($tenant, 'watermark_text', ''));
        $text = match ($format) {
            'artist' => $artist,
            'custom' => $custom !== '' ? $custom : $artist,
            'site' => $this->settings->get($tenant, 'site_title', $tenant->name),
            default => '© ' . date('Y') . ' ' . $artist,
        };
        $size = max(1, min(5, (int) $this->settings->get($tenant, 'watermark_size', '3')));
        $opacity = max(0.05, min(1.0, (float) $this->settings->get($tenant, 'watermark_opacity', '0.55')));
        $colorValue = strtolower($this->settings->get($tenant, 'watermark_color', 'white'));
        [$r,$g,$b] = $colorValue === 'black' ? [0,0,0] : [255,255,255];
        $color = imagecolorallocatealpha($image, $r, $g, $b, (int) round(127 * (1 - $opacity)));
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 72);
        $font = max(2, min(5, $size));
        $textWidth = imagefontwidth($font) * strlen($text); $textHeight = imagefontheight($font);
        $padding = max(10, (int) round(min($width,$height) * .02));
        $position = $this->settings->get($tenant, 'watermark_position', 'bottom-right');
        $x = str_contains($position, 'left') ? $padding : (str_contains($position, 'center') ? max($padding, (int)(($width-$textWidth)/2)) : max($padding, $width-$textWidth-$padding));
        $y = str_contains($position, 'top') ? $padding : (str_contains($position, 'center') ? max($padding, (int)(($height-$textHeight)/2)) : max($padding, $height-$textHeight-$padding));
        imagestring($image, $font, $x+1, $y+1, $text, $shadow);
        imagestring($image, $font, $x, $y, $text, $color);

        ob_start();
        match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, 88),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 88),
        };
        $bytes = ob_get_clean();
        return is_string($bytes) ? $bytes : null;
    }
}

// End of file.
