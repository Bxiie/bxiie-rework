<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use PDO;
use Throwable;

/**
 * Public marketing pages for artsfol.io.
 *
 * These pages are intentionally unauthenticated. They explain the platform,
 * provide public support/legal routes, and expose an opt-in tenant directory.
 */
final class MarketingController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function home(Request $request): Response
    {
        $tenantCards = $this->tenantCards(limit: 6);
        $imageMosaic = $this->imageMosaic(limit: 10);

        $body = <<<HTML
<section class="platform-hero">
    <div>
        <p class="eyebrow">Artist portfolio software with sales baked in</p>
        <h1>Build the art site you meant to have.</h1>
        <p class="hero-copy">ArtsFolio gives artists a fast, elegant portfolio, collector-ready artwork pages, email capture, contact tools, analytics, and room to grow into sales without rebuilding the whole machine later.</p>
        <div class="hero-actions">
            <a class="button primary" href="/signup">Start your portfolio</a>
            <a class="button secondary" href="/directory">Explore artists</a>
        </div>
        <div class="sso-row" aria-label="Single sign-on options">
            <a href="/auth/google">Continue with Google</a>
            <a href="/auth/facebook">Continue with Facebook</a>
            <a href="/login">Use email instead</a>
        </div>
    </div>
    <div class="hero-mosaic">
        {$imageMosaic}
    </div>
</section>

<section class="platform-section">
    <p class="eyebrow">Why ArtsFolio</p>
    <h2>Made for artists who need more than a pretty brochure.</h2>
    <div class="feature-grid">
        <article>
            <h3>Portfolio-first</h3>
            <p>Artwork pages, sections, public navigation, page images, exhibition history, and human-readable tenant CSS.</p>
        </article>
        <article>
            <h3>Collector-ready</h3>
            <p>Contact, interest capture, sales metadata, email signup, and future commerce flow without bolting on a junk drawer of plugins.</p>
        </article>
        <article>
            <h3>Admin that respects your time</h3>
            <p>Edit public content, organize artworks, review messages, manage subscribers, and see basic traffic from one place.</p>
        </article>
        <article>
            <h3>Custom domains</h3>
            <p>Use an artsfol.io subdomain by default or bring your own domain when your practice needs a more formal front door.</p>
        </article>
        <article>
            <h3>Search visibility</h3>
            <p>Clean public pages, useful metadata, sensible page structure, and a discovery directory for opted-in tenants.</p>
        </article>
        <article>
            <h3>Built to mature</h3>
            <p>OAuth, local login, tenant isolation, audit logs, and platform automation are part of the foundation, not a future apology.</p>
        </article>
    </div>
</section>

<section class="platform-section split">
    <div>
        <p class="eyebrow">New user flow</p>
        <h2>From blank wall to public portfolio.</h2>
    </div>
    <ol class="flow-list">
        <li><strong>Sign in</strong><span>Use Google, Facebook, or email/password.</span></li>
        <li><strong>Name your site</strong><span>Choose an artsfol.io subdomain or prepare a custom domain.</span></li>
        <li><strong>Add artwork</strong><span>Upload images, titles, medium, dimensions, sales status, and portfolio sections.</span></li>
        <li><strong>Publish pages</strong><span>Write home, about, contact, and exhibition content with optional HTML.</span></li>
        <li><strong>Invite collectors</strong><span>Enable email signup, contact forms, analytics, and directory opt-in.</span></li>
    </ol>
</section>

<section class="platform-section">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Opted-in artists</p>
            <h2>Discover work already living on ArtsFolio.</h2>
        </div>
        <a class="text-link" href="/directory">View directory</a>
    </div>
    <div class="tenant-card-grid">
        {$tenantCards}
    </div>
</section>

<section class="platform-cta">
    <h2>Your art deserves a site that can keep up.</h2>
    <p>Start simple. Grow into sales, analytics, custom domains, and collector workflows without changing platforms every time your practice evolves.</p>
    <a class="button primary" href="/signup">Create your ArtsFolio site</a>
</section>
HTML;

        return $this->page('ArtsFolio | Artist portfolio and sales platform', $body, 'home');
    }

    public function directory(Request $request): Response
    {
        $cards = $this->tenantCards(limit: 100);

        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Artist directory</p>
    <h1>Opted-in ArtsFolio sites</h1>
    <p>These artists have chosen to appear in the public ArtsFolio directory.</p>
</section>
<div class="tenant-card-grid directory-grid">
    {$cards}
</div>
HTML;

        return $this->page('Artist Directory | ArtsFolio', $body, 'directory');
    }

    public function signup(Request $request): Response
    {
        $body = <<<HTML
<section class="signup-panel">
    <p class="eyebrow">Start your site</p>
    <h1>Create your ArtsFolio account</h1>
    <p>Use SSO for the fastest start, or create a local account with email and password.</p>
    <div class="signup-actions">
        <a class="button primary" href="/auth/google">Continue with Google</a>
        <a class="button primary" href="/auth/facebook">Continue with Facebook</a>
        <a class="button secondary" href="/register">Use email/password</a>
    </div>
    <ol class="flow-list compact">
        <li><strong>Account</strong><span>Create or sign in.</span></li>
        <li><strong>Tenant</strong><span>Name your portfolio and choose a subdomain.</span></li>
        <li><strong>Artwork</strong><span>Upload first images and organize sections.</span></li>
        <li><strong>Publish</strong><span>Share your public site.</span></li>
    </ol>
</section>
HTML;

        return $this->page('Sign up | ArtsFolio', $body, 'signup');
    }

    public function contact(Request $request): Response
    {
        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Contact</p>
    <h1>Talk to ArtsFolio</h1>
    <p>For platform questions, onboarding help, billing, custom domains, or partnership inquiries, contact the ArtsFolio team.</p>
</section>
<form class="platform-form" method="post" action="/contact">
    <label>Name <input name="name" autocomplete="name"></label>
    <label>Email <input name="email" type="email" autocomplete="email"></label>
    <label>Message <textarea name="message" rows="8"></textarea></label>
    <button type="submit">Send message</button>
</form>
HTML;

        return $this->page('Contact | ArtsFolio', $body, 'contact');
    }

    public function help(Request $request): Response
    {
        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Help</p>
    <h1>ArtsFolio help</h1>
    <p>Useful starting points for artists setting up or managing their portfolio.</p>
</section>
<div class="feature-grid">
    <article><h3>Getting started</h3><p>Create an account, name your site, upload your first artworks, and publish your public pages.</p></article>
    <article><h3>Artwork management</h3><p>Use portfolio sections, image metadata, public/private status, and page images to shape the public site.</p></article>
    <article><h3>Discovery opt-in</h3><p>Tenant admins can choose whether their site appears in the public ArtsFolio directory and image mosaic.</p></article>
    <article><h3>Custom domains</h3><p>Higher tiers can connect a custom domain after DNS verification and platform approval.</p></article>
</div>
HTML;

        return $this->page('Help | ArtsFolio', $body, 'help');
    }

    public function privacy(Request $request): Response
    {
        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Privacy</p>
    <h1>Privacy overview</h1>
    <p>This page is a public placeholder for the ArtsFolio privacy policy. Replace it with reviewed legal copy before broad commercial launch.</p>
</section>
<div class="legal-copy">
    <h2>What ArtsFolio collects</h2>
    <p>Account information, tenant site content, uploaded artwork metadata, contact form submissions, email signup records, and operational analytics needed to run the platform.</p>
    <h2>Tenant visibility</h2>
    <p>Tenants are not shown in the public directory unless an admin opts in. Directory listings and random image features use only public artwork/site content from opted-in tenants.</p>
    <h2>Contact</h2>
    <p>Questions about privacy can be sent through the ArtsFolio contact page.</p>
</div>
HTML;

        return $this->page('Privacy | ArtsFolio', $body, 'privacy');
    }

    private function page(string $title, string $body, string $active): Response
    {
        $activeClass = static fn (string $key): string => $active === $key ? ' class="active"' : '';

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$this->escape($title)}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ArtsFolio is an artist portfolio and sales platform for working artists.">
    <link rel="stylesheet" href="/assets/platform.css">
</head>
<body>
<header class="platform-header">
    <a class="platform-brand" href="/">ArtsFolio</a>
    <nav>
        <a{$activeClass('home')} href="/">Home</a>
        <a{$activeClass('directory')} href="/directory">Artists</a>
        <a{$activeClass('help')} href="/help">Help</a>
        <a{$activeClass('contact')} href="/contact">Contact</a>
        <a class="login-link" href="/login">Sign in</a>
    </nav>
</header>
<main>
{$body}
</main>
<footer class="platform-footer">
    <span>© {$this->escape(date('Y'))} artsfol.io</span>
    <nav>
        <a href="/help">Help</a>
        <a href="/privacy">Privacy</a>
        <a href="/contact">Contact</a>
    </nav>
</footer>
</body>
</html>
HTML;

        return Response::html($html);
    }

    private function tenantCards(int $limit): string
    {
        $tenants = $this->optedInTenants($limit);

        if (!$tenants) {
            return '<article class="tenant-card empty"><h3>Directory opening soon</h3><p>Opted-in artists will appear here as the platform grows.</p></article>';
        }

        $html = '';
        foreach ($tenants as $tenant) {
            $name = $this->escape((string) ($tenant['display_name'] ?? $tenant['slug'] ?? 'Artist site'));
            $summary = $this->escape((string) ($tenant['summary'] ?? 'Artist portfolio on ArtsFolio.'));
            $href = $this->escape((string) ($tenant['href'] ?? '#'));

            $html .= <<<HTML
<a class="tenant-card" href="{$href}">
    <h3>{$name}</h3>
    <p>{$summary}</p>
    <span>Visit site</span>
</a>
HTML;
        }

        return $html;
    }

    private function imageMosaic(int $limit): string
    {
        $images = $this->optedInImages($limit);

        if (!$images) {
            return <<<HTML
<div class="mosaic-placeholder one"></div>
<div class="mosaic-placeholder two"></div>
<div class="mosaic-placeholder three"></div>
<div class="mosaic-placeholder four"></div>
HTML;
        }

        $html = '';
        foreach ($images as $image) {
            $src = $this->escape((string) $image['src']);
            $alt = $this->escape((string) ($image['alt'] ?? 'Artwork'));
            $href = $this->escape((string) ($image['href'] ?? '#'));
            $html .= "<a href=\"{$href}\"><img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\"></a>";
        }

        return $html;
    }

    private function optedInTenants(int $limit): array
    {
        try {
            $settingsTable = $this->settingsTable();
            if ($settingsTable === null) {
                return [];
            }

            $sql = "
                SELECT
                    t.id,
                    t.slug,
                    t.display_name,
                    COALESCE(summary.setting_value, '') AS summary,
                    COALESCE(domain.domain, CONCAT(t.slug, '.artsfol.io')) AS domain
                FROM tenants t
                INNER JOIN {$settingsTable} opt
                    ON opt.tenant_id = t.id
                   AND opt.setting_key = 'platform_directory_opt_in'
                   AND opt.setting_value IN ('1', 'true', 'yes', 'on')
                LEFT JOIN {$settingsTable} summary
                    ON summary.tenant_id = t.id
                   AND summary.setting_key = 'platform_directory_summary'
                LEFT JOIN tenant_domains domain
                    ON domain.tenant_id = t.id
                WHERE t.status = 'active'
                GROUP BY t.id, t.slug, t.display_name, summary.setting_value, domain.domain
                ORDER BY t.display_name ASC
                LIMIT :limit
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $domain = (string) ($row['domain'] ?? '');
                $row['href'] = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
            }

            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    private function optedInImages(int $limit): array
    {
        try {
            $settingsTable = $this->settingsTable();
            if ($settingsTable === null) {
                return [];
            }

            $sql = "
                SELECT
                    a.slug AS artwork_slug,
                    a.title,
                    m.uuid AS media_uuid,
                    COALESCE(domain.domain, CONCAT(t.slug, '.artsfol.io')) AS domain
                FROM tenants t
                INNER JOIN {$settingsTable} opt
                    ON opt.tenant_id = t.id
                   AND opt.setting_key = 'platform_directory_opt_in'
                   AND opt.setting_value IN ('1', 'true', 'yes', 'on')
                INNER JOIN artworks a
                    ON a.tenant_id = t.id
                   AND a.status = 'published'
                INNER JOIN media_assets m
                    ON m.id = a.primary_media_asset_id
                LEFT JOIN tenant_domains domain
                    ON domain.tenant_id = t.id
                WHERE t.status = 'active'
                ORDER BY RAND()
                LIMIT :limit
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $images = [];
            foreach ($rows as $row) {
                $domain = (string) ($row['domain'] ?? '');
                $base = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
                $uuid = (string) ($row['media_uuid'] ?? '');
                if ($uuid === '') {
                    continue;
                }

                $images[] = [
                    'src' => $base . '/media?uuid=' . rawurlencode($uuid),
                    'href' => $base . '/artwork/' . rawurlencode((string) ($row['artwork_slug'] ?? '')),
                    'alt' => (string) ($row['title'] ?? 'Artwork'),
                ];
            }

            return $images;
        } catch (Throwable) {
            return [];
        }
    }

    private function settingsTable(): ?string
    {
        foreach (['tenant_settings', 'settings'] as $table) {
            try {
                $stmt = $this->pdo->query("SHOW TABLES LIKE " . $this->pdo->quote($table));
                if ($stmt && $stmt->fetchColumn()) {
                    return $table;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
