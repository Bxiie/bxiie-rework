<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use PDO;
use Throwable;

/**
 * Public artist directory with useful empty-state guidance.
 */
final class DirectoryController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request): Response
    {
        $cards = $this->cards();
        $empty = $cards === '' ? '<article class="tenant-card empty"><h3>No artists are currently listed</h3><p>The directory is active, but no tenants have opted in yet. Tenant admins can enable discovery from tenant admin. Platform admins can manage the global directory switch from Platform Settings.</p><p><a class="button secondary" href="/help/directory">Read directory setup help</a></p></article>' : '';
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
        try {
            $sql = "SELECT t.slug, t.display_name, COALESCE(summary.setting_value, '') AS summary, COALESCE(domain.domain, CONCAT(t.slug, '.artsfol.io')) AS domain FROM tenants t INNER JOIN tenant_settings opt ON opt.tenant_id = t.id AND opt.setting_key = 'platform_directory_opt_in' AND opt.setting_value IN ('1','true','yes','on') LEFT JOIN tenant_settings summary ON summary.tenant_id = t.id AND summary.setting_key = 'platform_directory_summary' LEFT JOIN tenant_domains domain ON domain.tenant_id = t.id WHERE t.status = 'active' GROUP BY t.id, t.slug, t.display_name, summary.setting_value, domain.domain ORDER BY t.display_name ASC LIMIT 100";
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $rows = [];
        }

        $html = '';
        foreach ($rows as $row) {
            $name = self::escape((string) ($row['display_name'] ?: $row['slug'] ?: 'Artist site'));
            $summary = self::escape((string) ($row['summary'] ?: 'Artist portfolio on ArtsFolio.'));
            $domain = (string) ($row['domain'] ?? '#');
            $href = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
            $html .= '<a class="tenant-card" href="' . self::escape($href) . '"><h3>' . $name . '</h3><p>' . $summary . '</p><span>Visit site</span></a>';
        }

        return $html;
    }

    private function layout(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{$title}</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/platform.css"><link rel="stylesheet" href="/assets/platform-custom.css"><link rel="stylesheet" href="/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout"></head>
<body><header class="platform-header"><a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a href="/pricing">Pricing</a><a class="active" href="/directory">Artists</a><a href="/help">Help</a><a href="/login">Sign in</a></nav></header><main>{$body}</main><footer class="platform-footer"><span>© ArtsFolio</span><nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer></body></html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
