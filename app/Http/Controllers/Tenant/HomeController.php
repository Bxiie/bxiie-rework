<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;

/**
 * Handles tenant public site routes.
 */
final class HomeController
{
    public function __construct(
        private readonly TenantSettingsRepository $settings,
        private readonly ArtworkReadRepository $artworks,
        private readonly PDO $pdo,
        private readonly ?CsrfTokenService $csrf = null,
    ) {
    }

    public function home(Request $request, TenantContext $tenant): Response
    {
        $siteTitle = $this->escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $csrf = $this->csrf ? $this->escape($this->csrf->getOrCreate()) : '';

        return Response::html($this->layout(
            tenant: $tenant,
            title: $siteTitle,
            body: <<<HTML
<h1>{$siteTitle}</h1>
<h2>Stay in the loop</h2>
<form method="post" action="/signup">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p>
        <label>Name<br>
            <input type="text" name="name" autocomplete="name">
        </label>
    </p>
    <p>
        <label>Email<br>
            <input type="email" name="email" autocomplete="email" required>
        </label>
    </p>
    <button type="submit">Sign up</button>
</form>
HTML
        ));
    }

    public function portfolio(Request $request, TenantContext $tenant): Response
    {
        $sectionSlug = trim((string) ($_GET['section'] ?? ''));
        $sections = $this->artworks->activeSections($tenant);
        $items = $sectionSlug !== ''
            ? $this->artworks->publishedForSection($tenant, $sectionSlug, 240)
            : $this->artworks->latestPublished($tenant, 240);

        $body = "<h1>Portfolio</h1>\n";
        $body .= "<nav style=\"display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0 2rem;\">\n";
        $body .= "    <a href=\"/portfolio\" style=\"padding:.5rem .75rem;border:1px solid #222;text-decoration:none;\">All</a>\n";

        foreach ($sections as $section) {
            $slug = rawurlencode((string) $section['slug']);
            $name = $this->escape((string) $section['name']);
            $body .= "    <a href=\"/portfolio?section={$slug}\" style=\"padding:.5rem .75rem;border:1px solid #222;text-decoration:none;\">{$name}</a>\n";
        }

        $body .= "</nav>\n";

        if (!$items) {
            $body .= "<p>No published artwork yet.</p>\n";
        } else {
            $body .= "<div style=\"display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;\">\n";

            foreach ($items as $item) {
                $title = $this->escape((string) $item['title']);
                $slug = rawurlencode((string) $item['slug']);
                $year = $this->escape((string) ($item['year_created'] ?? ''));
                $medium = $this->escape((string) ($item['medium'] ?? ''));
                $image = '';

                if (!empty($item['media_uuid'])) {
                    $src = '/media?uuid=' . rawurlencode((string) $item['media_uuid']) . '&variant=thumb';
                    $alt = $this->escape((string) ($item['media_alt_text'] ?? $item['title']));
                    $image = "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" style=\"width:100%;height:240px;object-fit:contain;background:#fff;\">";
                }

                $body .= <<<HTML
<article style="border:1px solid #ddd;padding:1rem;background:#fffaf5;">
    <a href="/artwork/{$slug}">{$image}</a>
    <h2 style="font-size:1.1rem;margin:.75rem 0 .25rem;"><a href="/artwork/{$slug}">{$title}</a></h2>
    <p style="margin:.2rem 0;color:#666;">{$year}</p>
    <p style="margin:.2rem 0;color:#666;">{$medium}</p>
</article>
HTML;
            }

            $body .= "</div>\n";
        }

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | Portfolio",
            body: $body,
        ));
    }

    public function artwork(Request $request, TenantContext $tenant, string $slug): Response
    {
        $artwork = $this->artworks->findPublishedBySlug($tenant, $slug);

        if (!$artwork) {
            return Response::notFound("Artwork not found: {$slug}");
        }

        $title = $this->escape((string) $artwork['title']);
        $description = (string) ($artwork['description'] ?? '');
        $medium = $this->escape((string) ($artwork['medium'] ?? ''));
        $dimensions = $this->escape((string) ($artwork['dimensions'] ?? ''));
        $year = $this->escape((string) ($artwork['year_created'] ?? ''));

        $body = "<h1>{$title}</h1>\n";

        if (!empty($artwork['media_uuid'])) {
            $src = '/media?uuid=' . rawurlencode((string) $artwork['media_uuid']);
            $alt = $this->escape((string) ($artwork['media_alt_text'] ?? $artwork['title']));
            $body .= "<p><img src=\"{$src}\" alt=\"{$alt}\" style=\"max-width:720px;width:100%;height:auto;object-fit:contain;\"></p>\n";
        }

        $body .= "<p><strong>Medium:</strong> {$medium}</p>\n";
        $body .= "<p><strong>Dimensions:</strong> {$dimensions}</p>\n";
        $body .= "<p><strong>Year:</strong> {$year}</p>\n";
        $body .= "<div>{$description}</div>\n";

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$title} | {$this->escape($tenant->name)}",
            body: $body,
        ));
    }

    public function about(Request $request, TenantContext $tenant): Response
    {
        $about = $this->settings->get($tenant, 'about_content', '');
        $events = $this->events($tenant);
        $body = "<h1>About</h1>\n<article class=\"prose\">{$about}</article>\n";

        if ($events !== '') {
            $body .= "<section class=\"events\"><h2>Exhibitions</h2>{$events}</section>\n";
        }

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | About",
            body: $body
        ));
    }

    public function contact(Request $request, TenantContext $tenant): Response
    {
        $csrf = $this->csrf ? $this->escape($this->csrf->getOrCreate()) : '';

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | Contact",
            body: <<<HTML
<h1>Contact</h1>
<article class="prose">{$this->settings->get($tenant, 'contact_details', '')}</article>
<form method="post" action="/contact">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p>
        <label>Name<br>
            <input type="text" name="name" autocomplete="name" required>
        </label>
    </p>
    <p>
        <label>Email<br>
            <input type="email" name="email" autocomplete="email" required>
        </label>
    </p>
    <p>
        <label>Subject<br>
            <input type="text" name="subject">
        </label>
    </p>
    <p>
        <label>Message<br>
            <textarea name="message" rows="8" required></textarea>
        </label>
    </p>
    <button type="submit">Send message</button>
</form>
HTML
        ));
    }

    private function layout(TenantContext $tenant, string $title, string $body): string
    {
        $siteTitle = $this->escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $browserTitle = $this->escape($title);
        $copyrightName = $this->escape($this->settings->get($tenant, 'copyright_name', $siteTitle));
        $year = date('Y');
        $primaryColor = $this->escape($this->settings->get($tenant, 'primary_color', '#111111'));
        $accentColor = $this->escape($this->settings->get($tenant, 'accent_color', '#c9a85f'));
        $backgroundColor = $this->escape($this->settings->get($tenant, 'background_color', '#f7f2e8'));
        $topbarBackgroundColor = $this->escape($this->settings->get($tenant, 'topbar_background_color', 'color-mix(in srgb, var(--bg), white 50%)'));
        $homeTab = $this->escape($this->settings->get($tenant, 'home_tab', 'Home'));
        $portfolioTab = $this->escape($this->settings->get($tenant, 'portfolio_tab', 'Portfolio'));
        $aboutTab = $this->escape($this->settings->get($tenant, 'about_tab', 'About'));
        $contactTab = $this->escape($this->settings->get($tenant, 'contact_tab', 'Contact'));
        $portfolioSlug = $this->escape($this->settings->get($tenant, 'portfolio_slug', 'portfolio'));
        $aboutSlug = $this->escape($this->settings->get($tenant, 'about_slug', 'about'));
        $contactSlug = $this->escape($this->settings->get($tenant, 'contact_slug', 'contact'));

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$browserTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Artist portfolio">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/tenant.css">
</head>
<body style="--primary:{$primaryColor};--accent:{$accentColor};--bg:{$backgroundColor};--topbar-bg:{$topbarBackgroundColor};">
<header class="site-header">
    <a class="brand" href="/">{$siteTitle}</a>
    <nav>
        <a href="/">{$homeTab}</a>
        <a href="/{$portfolioSlug}">{$portfolioTab}</a>
        <a href="/{$aboutSlug}">{$aboutTab}</a>
        <a href="/{$contactSlug}">{$contactTab}</a>
    </nav>
</header>
<main class="site-main">
{$body}
</main>
<footer class="site-footer">© {$year} {$copyrightName}</footer>
</body>
</html>
HTML;
    }

    private function events(TenantContext $tenant): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT exhibition_date, name, exhibition_type, location, city, state_region, work_name, notes
             FROM exhibitions
             WHERE tenant_id = :tenant_id
               AND status = 'active'
             ORDER BY sort_order ASC, id DESC"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return '';
        }

        $mode = $this->settings->get($tenant, 'exhibitions_display_mode', 'text');

        if ($mode === 'table') {
            $html = '<table class="events-table"><tr><th>Date</th><th>Exhibition</th><th>Type</th><th>Location</th><th>Work</th><th>Additional information</th></tr>';
            foreach ($rows as $event) {
                $date = $this->escape((string) ($event['exhibition_date'] ?? ''));
                $name = $this->escape((string) $event['name']);
                $type = $this->escape((string) ($event['exhibition_type'] ?? ''));
                $locationRaw = (string) (($event['location'] ?? '') ?: (($event['city'] ?? '') . ', ' . ($event['state_region'] ?? '')));
                $location = $this->escape(trim($locationRaw, ', '));
                $work = $this->escape((string) ($event['work_name'] ?? ''));
                $notes = (string) ($event['notes'] ?? '');
                $html .= "<tr><td>{$date}</td><td>{$name}</td><td>{$type}</td><td>{$location}</td><td>{$work}</td><td><div class=\"prose small\">{$notes}</div></td></tr>";
            }
            return $html . '</table>';
        }

        $html = '';
        foreach ($rows as $event) {
            $date = $this->escape((string) ($event['exhibition_date'] ?? ''));
            $name = $this->escape((string) $event['name']);
            $type = $this->escape((string) ($event['exhibition_type'] ?? ''));
            $locationRaw = (string) (($event['location'] ?? '') ?: (($event['city'] ?? '') . ', ' . ($event['state_region'] ?? '')));
            $location = $this->escape(trim($locationRaw, ', '));
            $work = $this->escape((string) ($event['work_name'] ?? ''));
            $notes = (string) ($event['notes'] ?? '');

            $html .= "<article class=\"event-row\">";
            $html .= "<div class=\"event-date\">{$date}</div><div>";
            $html .= "<h3>{$name}</h3>";
            if ($type !== '') {
                $html .= "<p><strong>{$type}</strong></p>";
            }
            if ($location !== '') {
                $html .= "<p>{$location}</p>";
            }
            if ($work !== '') {
                $html .= "<p>{$work}</p>";
            }
            if ($notes !== '') {
                $html .= "<div class=\"prose small\">{$notes}</div>";
            }
            $html .= "</div></article>\n";
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
