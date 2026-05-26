<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use PDO;
use Throwable;

/**
 * Public artist directory. Reads the same tenant settings written by tenant
 * admin: opt-in flag, summary, and selected thumbnail artwork.
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
    <h1>Artist directory is currently off</h1>
    <p>The ArtsFolio directory is disabled by platform settings. Platform admins can turn it on from Platform Admin → Platform Settings.</p>
</section>
HTML;
            return Response::html($this->layout('Artist Directory | ArtsFolio', $body));
        }

        $cards = $this->cards();
        $empty = $cards === '' ? '<article class="tenant-card empty"><h3>No artists are currently listed</h3><p>The directory is active, but no tenants have opted in with a valid published thumbnail artwork yet. Tenant admins can enable discovery from Admin → Directory.</p><p><a class="button secondary" href="/help/directory">Read directory setup help</a></p></article>' : '';
        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Artist directory</p>
    <h1>Discover ArtsFolio artists</h1>
    <p>Artists appear here after the platform directory is enabled and their tenant admin opts into discovery.</p>
</section>
<div class="tenant-card-grid directory-grid">{$cards}{$empty}</div>
HTML;

        return Response::html($this->layout('Artist Directory | ArtsFolio', $body));
    }

    private function cards(): string
    {
        $rows = $this->directoryRows();
        $html = '';

        foreach ($rows as $row) {
            $name = self::escape((string) ($row['display_name'] ?: $row['slug'] ?: 'Artist site'));
            $summary = self::escape((string) ($row['summary'] ?: 'Artist portfolio on ArtsFolio.'));
            $domain = (string) ($row['domain'] ?? '#');
            $href = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
            $thumbnail = '';

            if (!empty($row['media_uuid'])) {
                $base = rtrim($href, '/');
                $src = self::escape($base . '/media?uuid=' . rawurlencode((string) $row['media_uuid']));
                $alt = self::escape((string) ($row['artwork_title'] ?: $row['display_name'] ?: 'Directory artwork'));
                $thumbnail = '<div class="directory-card-thumb"><img src="' . $src . '" alt="' . $alt . '" loading="lazy"></div>';
            }

            $html .= '<a class="tenant-card directory-card" href="' . self::escape($href) . '">' . $thumbnail . '<div><h3>' . $name . '</h3><p>' . $summary . '</p><span>Visit site</span></div></a>';
        }

        return $html;
    }

    /**
     * Uses runtime column detection because older patches used display_name/domain
     * while the current MariaDB schema uses name/hostname. This keeps the public
     * directory alive across the in-flight refactor.
     */
    private function directoryRows(): array
    {
        try {
            $tenantNameColumn = $this->columnExists('tenants', 'display_name') ? 'display_name' : 'name';
            $domainColumn = $this->columnExists('tenant_domains', 'domain') ? 'domain' : 'hostname';
            $domainStatusPredicate = $this->columnExists('tenant_domains', 'status') ? " AND domain.status = 'active'" : '';
            $domainPrimaryPredicate = $this->columnExists('tenant_domains', 'is_primary') ? ' AND domain.is_primary = TRUE' : '';

            $sql = "
                SELECT
                    t.id,
                    t.slug,
                    t.{$tenantNameColumn} AS display_name,
                    COALESCE(summary.setting_value, '') AS summary,
                    COALESCE(domain.{$domainColumn}, CONCAT(t.slug, '.artsfol.io')) AS domain,
                    a.title AS artwork_title,
                    m.uuid AS media_uuid
                FROM tenants t
                INNER JOIN tenant_settings opt
                    ON opt.tenant_id = t.id
                   AND opt.setting_key = 'platform_directory_opt_in'
                   AND LOWER(TRIM(opt.setting_value)) IN ('1', 'true', 'yes', 'on')
                LEFT JOIN tenant_settings summary
                    ON summary.tenant_id = t.id
                   AND summary.setting_key = 'platform_directory_summary'
                LEFT JOIN tenant_settings thumb
                    ON thumb.tenant_id = t.id
                   AND thumb.setting_key = 'platform_directory_thumbnail_artwork_id'
                LEFT JOIN artworks a
                    ON a.tenant_id = t.id
                   AND a.id = CAST(NULLIF(thumb.setting_value, '') AS UNSIGNED)
                   AND a.status = 'published'
                LEFT JOIN media_assets m
                    ON m.id = a.primary_media_id
                LEFT JOIN tenant_domains domain
                    ON domain.tenant_id = t.id
                   {$domainPrimaryPredicate}
                   {$domainStatusPredicate}
                WHERE t.status = 'active'
                ORDER BY t.{$tenantNameColumn} ASC
                LIMIT 100
            ";

            $stmt = $this->pdo->query($sql);
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            error_log('ArtsFolio directory tenant query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function platformDirectoryEnabled(): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_directory_enabled' LIMIT 1");
            $stmt->execute();
            $value = $stmt->fetchColumn();

            if ($value === false || $value === null || $value === '') {
                return true;
            }

            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
        } catch (Throwable) {
            return true;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name
                   AND column_name = :column_name"
            );
            $stmt->execute([
                'table_name' => $table,
                'column_name' => $column,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function layout(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{$title}</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/platform.css"><link rel="stylesheet" href="/assets/platform-custom.css"><link rel="stylesheet" href="/assets/tenant-admin.css"><style>.directory-card{overflow:hidden;padding:0}.directory-card>div:not(.directory-card-thumb){padding:1rem}.directory-card-thumb{aspect-ratio:4/3;background:#f3efe7;overflow:hidden}.directory-card-thumb img{width:100%;height:100%;object-fit:cover;display:block}</style></head>
<body><header class="platform-header"><a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a href="/pricing">Pricing</a><a class="active" href="/directory">Artists</a><a href="/help">Help</a><a href="/login">Sign in</a></nav></header><main>{$body}</main><footer class="platform-footer"><span>© ArtsFolio</span><nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer></body></html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
