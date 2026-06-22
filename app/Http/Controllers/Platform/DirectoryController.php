<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Directory\TenantDirectoryProfileRepository;
use PDO;
use Throwable;

/** Public artist directory backed by the denormalized directory projection. */
final class DirectoryController
{
    private const PER_PAGE = 24;

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

        $page = max(1, (int) ($_GET['page'] ?? 1));
        [$cards, $total] = $this->cards($page);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
            [$cards, $total] = $this->cards($page);
        }

        $empty = $cards === ''
            ? '<article class="tenant-card empty"><h3>No artists are currently listed</h3><p>The directory is active, but no tenants have opted in yet.</p><p><a class="button secondary" href="/help/directory">Read directory setup help</a></p></article>'
            : '';
        $pager = $this->pager($page, $totalPages, $total);
        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Artist directory</p>
    <h1>Discover ArtsFolio artists</h1>
    <p>Artists appear here after the platform directory is enabled and their tenant admin opts into discovery.</p>
</section>
{$pager}
<div class="tenant-card-grid directory-grid" style="{\App\Http\View\PlatformChrome::directoryThumbnailStyle()}">{$cards}{$empty}</div>
{$pager}
HTML;

        return Response::html($this->layout('Artist Directory | ArtsFolio', $body));
    }

    /** @return array{0:string,1:int} */
    private function cards(int $page): array
    {
        try {
            $repository = new TenantDirectoryProfileRepository($this->pdo);
            $rows = $repository->page($page, self::PER_PAGE);
            $total = $repository->listedCount();
        } catch (Throwable $e) {
            error_log('ArtsFolio directory projection query failed: ' . $e->getMessage());
            return ['', 0];
        }

        $html = '';
        foreach ($rows as $row) {
            $name = self::escape((string) ($row['display_name'] ?: 'Artist site'));
            $summary = self::escape((string) ($row['summary'] ?: 'Artist portfolio on ArtsFolio.'));
            $domain = (string) ($row['primary_hostname'] ?? '');
            if ($domain === '') {
                continue;
            }
            $href = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
            $thumbnailUuid = (string) ($row['thumbnail_media_uuid'] ?? '');
            $thumbnail = '';
            if ($thumbnailUuid !== '') {
                $src = self::escape($href . '/media?uuid=' . rawurlencode($thumbnailUuid) . '&variant=thumb');
                $alt = self::escape((string) ($row['thumbnail_title'] ?: $name));
                $thumbnail = '<div class="directory-card-thumb"><img src="' . $src . '" alt="' . $alt . '" loading="lazy"></div>';
            }

            $html .= '<a class="tenant-card directory-card" href="' . self::escape($href) . '">' . $thumbnail . '<h3>' . $name . '</h3><p>' . $summary . '</p><span>Visit site</span></a>';
        }

        return [$html, $total];
    }

    private function pager(int $page, int $totalPages, int $total): string
    {
        if ($total <= self::PER_PAGE) {
            return $total > 0 ? '<p class="directory-count">' . $total . ' artists</p>' : '';
        }

        $previous = $page > 1
            ? '<a class="button secondary" href="/directory?page=' . ($page - 1) . '">‹ Previous</a>'
            : '<span class="button secondary" aria-disabled="true">‹ Previous</span>';
        $next = $page < $totalPages
            ? '<a class="button secondary" href="/directory?page=' . ($page + 1) . '">Next ›</a>'
            : '<span class="button secondary" aria-disabled="true">Next ›</span>';

        return '<nav class="directory-pager" aria-label="Artist directory pages" style="display:flex;gap:.75rem;align-items:center;justify-content:center;margin:1rem 0;">'
            . $previous
            . '<span>Page ' . $page . ' of ' . $totalPages . ' · ' . $total . ' artists</span>'
            . $next
            . '</nav>';
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
