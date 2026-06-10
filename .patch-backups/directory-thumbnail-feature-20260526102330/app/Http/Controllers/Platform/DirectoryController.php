<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use PDO;
use Throwable;

/**
 * Renders the public artist directory from the same tenant_settings keys that
 * tenant admins update from /admin/directory.
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
    <h1>The ArtsFolio directory is currently off</h1>
    <p>A platform admin can enable the public artist directory from Platform Settings.</p>
</section>
<article class="tenant-card empty"><h3>Directory disabled</h3><p>The global directory switch is off. Tenant opt-in settings are preserved and will be used again when the directory is re-enabled.</p></article>
HTML;
            return Response::html($this->layout('Artist Directory | ArtsFolio', $body));
        }

        $cards = $this->cards();
        $empty = $cards === '' ? '<article class="tenant-card empty"><h3>No artists are currently listed</h3><p>The directory is active, but no active tenant has opted in with <code>platform_directory_opt_in</code>. Tenant admins can enable this from <strong>Admin → Directory</strong>.</p><p><a class="button secondary" href="/help/directory">Read directory setup help</a></p></article>' : '';
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
            $domain = trim((string) ($row['domain'] ?? ''));
            $slug = trim((string) ($row['slug'] ?? ''));
            $host = $domain !== '' ? $domain : ($slug !== '' ? $slug . '.artsfol.io' : 'artsfol.io');
            $href = str_starts_with($host, 'http://') || str_starts_with($host, 'https://') ? $host : 'https://' . $host;
            $html .= '<a class="tenant-card" href="' . self::escape($href) . '"><h3>' . $name . '</h3><p>' . $summary . '</p><span>Visit site</span></a>';
        }

        return $html;
    }

    /**
     * Query directory rows across the current MariaDB schema and the older
     * SQLite development schema. The important contract is the setting key:
     * tenant admins write platform_directory_opt_in to tenant_settings.
     */
    private function directoryRows(): array
    {
        try {
            $tenantsNameColumn = $this->columnExists('tenants', 'name') ? 'name' : 'display_name';
            $settingsTable = $this->tableExists('tenant_settings') ? 'tenant_settings' : 'settings';
            $domainColumn = $this->columnExists('tenant_domains', 'hostname') ? 'hostname' : 'domain';
            $hasPrimary = $this->columnExists('tenant_domains', 'is_primary');
            $hasDomainStatus = $this->columnExists('tenant_domains', 'status');

            $domainJoinFilters = [];
            if ($hasPrimary) {
                $domainJoinFilters[] = 'domain.is_primary = 1';
            }
            if ($hasDomainStatus) {
                $domainJoinFilters[] = "domain.status IN ('active', 'dns_verified', 'vhost_pending', 'cert_pending')";
            }
            $domainJoinSql = $domainJoinFilters === [] ? '' : ' AND ' . implode(' AND ', $domainJoinFilters);

            $sql = "
                SELECT
                    t.id,
                    t.slug,
                    t.{$tenantsNameColumn} AS display_name,
                    COALESCE(summary.setting_value, '') AS summary,
                    COALESCE(domain.{$domainColumn}, CONCAT(t.slug, '.artsfol.io')) AS domain
                FROM tenants t
                INNER JOIN {$settingsTable} opt
                    ON opt.tenant_id = t.id
                   AND opt.setting_key = 'platform_directory_opt_in'
                   AND LOWER(TRIM(COALESCE(opt.setting_value, ''))) IN ('1', 'true', 'yes', 'on')
                LEFT JOIN {$settingsTable} summary
                    ON summary.tenant_id = t.id
                   AND summary.setting_key = 'platform_directory_summary'
                LEFT JOIN tenant_domains domain
                    ON domain.tenant_id = t.id{$domainJoinSql}
                WHERE t.status IN ('active', 'trial')
                GROUP BY t.id, t.slug, t.{$tenantsNameColumn}, summary.setting_value, domain.{$domainColumn}
                ORDER BY t.{$tenantsNameColumn} ASC
                LIMIT 100
            ";

            $stmt = $this->pdo->query($sql);
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            error_log('ArtsFolio directory query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function platformDirectoryEnabled(): bool
    {
        try {
            if (!$this->tableExists('platform_settings')) {
                return true;
            }

            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_directory_enabled' LIMIT 1");
            $stmt->execute();
            $value = $stmt->fetchColumn();
            if ($value === false || $value === null || trim((string) $value) === '') {
                return true;
            }

            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
        } catch (Throwable $e) {
            error_log('ArtsFolio directory setting check failed: ' . $e->getMessage());
            return true;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            try {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1");
                $stmt->execute(['name' => $table]);
                return $stmt->fetchColumn() !== false;
            } catch (Throwable) {
                return false;
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . $this->pdo->quote($column));
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            try {
                $stmt = $this->pdo->query('PRAGMA table_info(' . str_replace(')', '', $table) . ')');
                foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    private function layout(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{$title}</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/platform.css"><link rel="stylesheet" href="/assets/platform-custom.css"><link rel="stylesheet" href="/assets/tenant-admin.css"></head>
<body><header class="platform-header"><a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a href="/pricing">Pricing</a><a class="active" href="/directory">Artists</a><a href="/help">Help</a><a href="/login">Sign in</a></nav></header><main>{$body}</main><footer class="platform-footer"><span>© ArtsFolio</span><nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer></body></html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
