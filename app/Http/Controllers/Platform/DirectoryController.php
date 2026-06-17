<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use PDO;
use Throwable;

/**
 * Public artist directory.
 *
 * The directory intentionally reads the same tenant_settings keys written by
 * the tenant admin directory screen. Keep this query in lock-step with
 * DiscoverySettingsController and scripts/debug/check_directory_thumbnail_contract.php.
 */
final class DirectoryController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request): Response
    {
        if (!$this->platformDirectoryEnabled()) {
            $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Artist directory</p>
    <h1>The ArtsFolio directory is currently disabled.</h1>
    <p>A platform admin can enable the public directory from platform settings.</p>
</section>
HTML;
            return Response::html($this->layout('Artist Directory | ArtsFolio', $body));
        }

        $cards = $this->cards();
        $empty = $cards === '' ? '<article class="tenant-card empty"><h3>No artists are currently listed</h3><p>The directory is active, but no tenants have opted in yet. Tenant admins can enable discovery from tenant admin. Platform admins can manage the global directory switch from Platform Settings.</p><p><a class="button secondary" href="/help/directory">Read directory setup help</a></p></article>' : '';
        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Artist directory</p>
    <h1>Discover ArtsFolio artists</h1>
    <p>Artists appear here after the platform directory is enabled and their tenant admin opts into discovery.</p>
</section>
<div class="tenant-card-grid directory-grid" style="{\App\Http\View\PlatformChrome::directoryThumbnailStyle()}">{$cards}{$empty}</div>
HTML;

        return Response::html($this->layout('Artist Directory | ArtsFolio', $body));
    }

    private function cards(): string
    {
        try {
            $settingsTable = $this->settingsTable();
            if ($settingsTable === null) {
                return '';
            }

            $sql = <<<SQL
SELECT
    t.id,
    t.slug,
    t.name AS display_name,
    COALESCE(summary.setting_value, '') AS summary,
    COALESCE(primary_domain.hostname, fallback_domain.hostname, CONCAT(t.slug, '.artsfol.io')) AS domain,
    thumbnail_media.uuid AS thumbnail_uuid,
    thumbnail_artwork.title AS thumbnail_title
FROM tenants t
INNER JOIN {$settingsTable} opt
    ON opt.tenant_id = t.id
   AND opt.setting_key = 'platform_directory_opt_in'
   AND LOWER(TRIM(opt.setting_value)) IN ('1', 'true', 'yes', 'on')
LEFT JOIN {$settingsTable} summary
    ON summary.tenant_id = t.id
   AND summary.setting_key = 'platform_directory_summary'
LEFT JOIN {$settingsTable} selected_thumbnail
    ON selected_thumbnail.tenant_id = t.id
   AND selected_thumbnail.setting_key = 'platform_directory_thumbnail_artwork_id'
LEFT JOIN artworks thumbnail_artwork
    ON thumbnail_artwork.tenant_id = t.id
   AND thumbnail_artwork.id = CAST(NULLIF(selected_thumbnail.setting_value, '') AS UNSIGNED)
   AND thumbnail_artwork.status = 'published'
LEFT JOIN media_assets thumbnail_media
    ON thumbnail_media.id = thumbnail_artwork.primary_media_id
   AND thumbnail_media.is_private = 0
LEFT JOIN tenant_domains primary_domain
    ON primary_domain.tenant_id = t.id
   AND primary_domain.is_primary = TRUE
   AND primary_domain.status = 'active'
LEFT JOIN tenant_domains fallback_domain
    ON fallback_domain.id = (
        SELECT td.id
        FROM tenant_domains td
        WHERE td.tenant_id = t.id
          AND td.status = 'active'
        ORDER BY td.is_primary DESC, td.id ASC
        LIMIT 1
    )
WHERE t.status = 'active'
ORDER BY t.name ASC
LIMIT 100
SQL;
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('ArtsFolio directory tenant query failed: ' . $e->getMessage());
            $rows = [];
        }

        $html = '';
        foreach ($rows as $row) {
            $name = self::escape((string) ($row['display_name'] ?: $row['slug'] ?: 'Artist site'));
            $summary = self::escape((string) ($row['summary'] ?: 'Artist portfolio on ArtsFolio.'));
            $domain = (string) ($row['domain'] ?? '#');
            $href = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
            $thumbnailUuid = (string) ($row['thumbnail_uuid'] ?? '');
            $thumbnail = '';
            if ($thumbnailUuid !== '') {
                $src = self::escape($href . '/media?uuid=' . rawurlencode($thumbnailUuid) . '&variant=thumb');
                $alt = self::escape((string) ($row['thumbnail_title'] ?: $name));
                $thumbnail = '<div class="directory-card-thumb"><img src="' . $src . '" alt="' . $alt . '" loading="lazy"></div>';
            }

            $html .= '<a class="tenant-card directory-card" href="' . self::escape($href) . '">' . $thumbnail . '<h3>' . $name . '</h3><p>' . $summary . '</p><span>Visit site</span></a>';
        }

        return $html;
    }

    private function platformDirectoryEnabled(): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_directory_enabled' LIMIT 1");
            $stmt->execute();
            $value = $stmt->fetchColumn();
            if ($value === false) {
                return true;
            }

            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
        } catch (Throwable) {
            return true;
        }
    }

    private function settingsTable(): ?string
    {
        foreach (['tenant_settings', 'settings'] as $table) {
            try {
                $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
                if ($stmt && $stmt->fetchColumn()) {
                    return $table;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function layout(string $title, string $body): string
    {
        $platformAdminLink = \App\Http\View\PlatformChrome::platformAdminLink();
        $platformCopyright = \App\Http\View\PlatformChrome::copyrightLine();

        return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{$title}</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/platform.css"><link rel="stylesheet" href="/assets/platform-custom.css"><link rel="stylesheet" href="/assets/tenant-admin.css"></head>
<body><header class="platform-header"><a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a href="/pricing">Pricing</a><a class="active" href="/directory">Artists</a><a href="/help">Help</a>{$platformAdminLink}<a href="/login">Sign in</a></nav></header><main>{$body}</main><footer class="platform-footer"><span>{$platformCopyright}</span><nav><a href="/help">Help</a><a href="/terms">Terms</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer></body></html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
