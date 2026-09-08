<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Renders editable social caption templates from public artwork metadata.
 *
 * Internal notes are intentionally not exposed as placeholders.
 */
final class SocialTemplateRenderer
{
    public function __construct(private readonly TenantSettingsRepository $settings)
    {
    }

    /** @return array<string,string> */
    public function values(TenantContext $tenant, array $artwork, string $host): array
    {
        $artist = trim((string) $this->settings->get($tenant, 'artist_name', $tenant->name));
        $website = 'https://' . $host;
        $artworkUrl = $website . '/artwork/' . rawurlencode((string) ($artwork['slug'] ?? ''));
        $portfolioSlug = trim((string) $this->settings->get($tenant, 'portfolio_slug', 'portfolio')) ?: 'portfolio';
        $portfolioUrl = $website . '/' . rawurlencode($portfolioSlug);
        $description = trim(strip_tags((string) ($artwork['description'] ?? '')));
        $socialCaption = trim((string) ($artwork['social_caption'] ?? ''));
        $year = trim((string) ($artwork['year_created'] ?? ''));
        $medium = trim((string) ($artwork['medium'] ?? ''));
        $dimensions = trim((string) ($artwork['dimensions'] ?? ''));
        $meta = implode(' · ', array_values(array_filter([$year, $medium, $dimensions], static fn (string $value): bool => $value !== '')));
        $price = trim((string) ($artwork['price'] ?? ''));
        $availability = match ((string) ($artwork['sale_status'] ?? '')) {
            'for_sale' => 'Available',
            'sold' => 'Sold',
            default => 'Not for sale',
        };
        $sectionNames = str_replace('||', ', ', trim((string) ($artwork['section_names'] ?? '')));
        $copyright = trim((string) $this->settings->get($tenant, 'copyright_name', $artist));
        $defaultHashtags = $this->normalizeHashtags((string) $this->settings->get($tenant, 'instagram_default_hashtags', ''));
        $artworkHashtags = $this->normalizeHashtags((string) ($artwork['social_hashtags'] ?? ''));
        $allHashtags = $this->mergeHashtags($defaultHashtags, $artworkHashtags);

        return [
            'title' => trim((string) ($artwork['title'] ?? '')),
            'artist_name' => $artist,
            'artist' => $artist,
            'year' => $year,
            'medium' => $medium,
            'dimensions' => $dimensions,
            'year_medium_dimensions' => $meta,
            'description' => $description,
            'social_caption' => $socialCaption,
            'social_or_description' => $socialCaption !== '' ? $socialCaption : $description,
            'artwork_url' => $artworkUrl,
            'website' => $website,
            'site_url' => $website,
            'portfolio_url' => $portfolioUrl,
            'portfolio_name' => trim((string) $this->settings->get($tenant, 'portfolio_tab', 'Portfolio')),
            'section_name' => $sectionNames,
            'section_names' => $sectionNames,
            'price' => $price,
            'availability' => $availability,
            'copyright_year' => date('Y'),
            'copyright_holder' => $copyright,
            'default_hashtags' => $defaultHashtags,
            'artwork_hashtags' => $artworkHashtags,
            'hashtags' => $allHashtags,
        ];
    }

    public function render(string $template, array $values): string
    {
        $rendered = preg_replace_callback('/\{([a-z0-9_]+)\}/i', static function (array $matches) use ($values): string {
            return (string) ($values[strtolower($matches[1])] ?? '');
        }, $template) ?? $template;

        $lines = preg_split('/\R/', $rendered) ?: [$rendered];
        $clean = [];
        $blank = false;
        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '') {
                if (!$blank && $clean !== []) {
                    $clean[] = '';
                }
                $blank = true;
                continue;
            }
            $clean[] = $line;
            $blank = false;
        }

        return trim(implode("\n", $clean));
    }

    public function normalizeHashtags(string $input): string
    {
        $parts = preg_split('/[\s,]+/', trim($input)) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $part = preg_replace('/[^\pL\pN_#]/u', '', trim($part)) ?? '';
            $part = ltrim($part, '#');
            if ($part === '') {
                continue;
            }
            $key = mb_strtolower($part);
            $normalized[$key] = '#' . $part;
        }
        return implode(' ', array_values($normalized));
    }

    public function mergeHashtags(string ...$groups): string
    {
        return $this->normalizeHashtags(implode(' ', $groups));
    }
}

// End of file.
