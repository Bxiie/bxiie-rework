<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;

/**
 * Renders the combined ArtsFolio help and developer reference section.
 *
 * The controller intentionally supports both topic() and article() because
 * older route bundles used different method names.  Keeping both avoids a
 * production white-screen when one route variant survives in public/index.php.
 */
final class HelpController
{
    /** @var array<string, array{title:string, body:string}> */
    private array $articles;

    public function __construct()
    {
        $this->articles = [
            'getting-started' => [
                'title' => 'Getting started',
                'body' => '<p>ArtsFolio turns an artist site into a managed portfolio, contact, event, sales-readiness, and discovery platform. Start by signing in, opening the tenant admin, setting branding, and publishing a first artwork.</p><ol class="flow-list compact"><li><strong>Open admin</strong><span>Use the Admin link after login.</span></li><li><strong>Brand the site</strong><span>Set artist name, public labels, colors, images, and CSS.</span></li><li><strong>Add artwork</strong><span>Upload artwork and assign portfolio sections.</span></li><li><strong>Turn on discovery</strong><span>Use Admin → Directory when the artist is ready to appear publicly.</span></li></ol>',
            ],
            'branding' => [
                'title' => 'Branding and CSS',
                'body' => '<p>Tenant admins manage artist-site colors, copy, page labels, CSS, logos, and navigation from tenant admin. Platform admins manage the public ArtsFolio look and feel separately from tenant branding.</p>',
            ],
            'artworks' => [
                'title' => 'Artwork management',
                'body' => '<p>Use Artworks to upload images, add title, medium, dimensions, price/status notes, and assign portfolio sections. Published artwork appears on public tenant pages and may be eligible for directory features when the tenant opts in.</p>',
            ],
            'events' => [
                'title' => 'Events and exhibitions',
                'body' => '<p>Use Events for exhibitions, fairs, talks, residencies, open studios, and other date-based history. Filtering and ordering keep long CV-style histories readable.</p>',
            ],
            'directory' => [
                'title' => 'Artist directory',
                'body' => '<p>The directory has two gates. Platform admins enable the directory globally. Tenant admins opt the individual artist into the directory from <strong>Admin → Directory</strong>. Tenants stay hidden until both gates are open.</p><ol class="flow-list compact"><li><strong>Tenant admin</strong><span>Go to /admin/directory on the tenant domain.</span></li><li><strong>Enable listing</strong><span>Check “Show this tenant in the public ArtsFolio directory.”</span></li><li><strong>Add summary</strong><span>Write a short public description for directory cards.</span></li><li><strong>Save</strong><span>The artist can then appear on artsfol.io/directory when the platform directory is enabled.</span></li></ol>',
            ],
            'stats' => [
                'title' => 'Stats',
                'body' => '<p>Tenant stats show tenant traffic and content engagement. Platform stats show platform-wide traffic and operational totals. Empty stats usually mean analytics events are not being written or the route is being reached through the wrong host.</p>',
            ],
            'audit' => [
                'title' => 'Audit log',
                'body' => '<p>Audit entries record login, security, and administrative changes. Tenant audit pages should show tenant-scoped admin actions. Platform audit pages show platform administration and authentication events.</p>',
            ],
        ];
    }

    public function index(Request $request, ?array $currentUser = null): Response
    {
        return $this->topic($request, 'getting-started', $currentUser);
    }

    public function topic(Request $request, string|array $slug = 'getting-started', ?array $currentUser = null): Response
    {
        return $this->article($request, $slug, $currentUser);
    }

    public function article(Request $request, string|array $slug = 'getting-started', ?array $currentUser = null): Response
    {
        if (is_array($slug)) {
            $slug = (string) ($slug['article'] ?? $slug['topic'] ?? $slug['slug'] ?? 'getting-started');
        }

        $slug = trim($slug, '/');
        if ($slug === '') {
            $slug = 'getting-started';
        }

        if ($slug === 'developer') {
            return $this->developer($request, $currentUser);
        }

        $article = $this->articles[$slug] ?? null;
        if (!$article) {
            return Response::html($this->layout('Help article not found', '<p class="admin-error">That help article does not exist.</p>', '', $currentUser), 404);
        }

        return Response::html($this->layout($article['title'], $article['body'], $slug, $currentUser));
    }

    public function developer(Request $request, ?array $currentUser = null): Response
    {
        if (!$currentUser && isset($GLOBALS['artsfolio_current_user']) && is_array($GLOBALS['artsfolio_current_user'])) {
            $currentUser = $GLOBALS['artsfolio_current_user'];
        }

        if (!$currentUser) {
            return new Response('', 302, ['Location' => '/login?next=/help/developer']);
        }

        $body = <<<HTML
<p class="admin-muted">Developer information is visible only after login. This reference gives each major browser/API route a practical usage description and a copy-pasteable example.</p>

<h2>How to read this reference</h2>
<p>Examples assume the command is run from a trusted workstation and that browser-authenticated routes use the same session cookie a user receives after login. Replace <code>https://artsfol.io</code>, tenant hostnames, IDs, and form values with real deployment values.</p>

<h2>Browser authentication</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /login</h3><p>Render the branded login form. Use this route when redirecting a browser user who needs to authenticate before reaching admin, developer, or account pages.</p><pre><code>curl -i https://artsfol.io/login</code></pre></article>
    <article><h3>POST /login</h3><p>Submit local email/password credentials from the branded login form. A successful response sets the browser session cookie and redirects the user.</p><pre><code>curl -i -X POST https://artsfol.io/login \
  -d 'email=admin@example.com' \
  -d 'password=replace-with-real-password'</code></pre></article>
    <article><h3>POST /login/password</h3><p>Backward-compatible password-login endpoint retained for older forms and scripts. Prefer <code>POST /login</code> for new browser form work unless you are maintaining an old flow.</p><pre><code>curl -i -X POST https://artsfol.io/login/password \
  -d 'email=admin@example.com' \
  -d 'password=replace-with-real-password'</code></pre></article>
    <article><h3>POST /logout</h3><p>Clear the active browser session. Use this from logout buttons in authenticated platform or tenant admin screens.</p><pre><code>curl -i -X POST https://artsfol.io/logout \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE'</code></pre></article>
</div>

<h2>Platform public routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /</h3><p>Render the ArtsFolio public landing page. Use this as the canonical platform homepage for marketing, directory entry points, help links, and signup calls to action.</p><pre><code>curl -i https://artsfol.io/</code></pre></article>
    <article><h3>GET /pricing</h3><p>Render public plan and pricing information. Use this route from marketing pages, emails, and onboarding flows where users compare tiers.</p><pre><code>curl -i https://artsfol.io/pricing</code></pre></article>
    <article><h3>GET /signup</h3><p>Render the tenant signup form. Use this for new artists or organizations starting an ArtsFolio tenant.</p><pre><code>curl -i https://artsfol.io/signup</code></pre></article>
    <article><h3>POST /signup</h3><p>Create a tenant, public slug, initial owner user, membership, and provisioning jobs. The form is CSRF-protected in the browser path, so scripts should be limited to controlled tests unless an API-specific signup endpoint is added.</p><pre><code>curl -i -X POST https://artsfol.io/signup \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'site_name=Example Studio' \
  -d 'slug=example-studio' \
  -d 'admin_name=Example Admin' \
  -d 'email=admin@example.com' \
  -d 'password=long-development-password'</code></pre></article>
    <article><h3>GET /directory</h3><p>Render the public artist directory. Results require the platform directory to be enabled and each listed tenant to have opted into discovery.</p><pre><code>curl -i https://artsfol.io/directory</code></pre></article>
    <article><h3>GET /help</h3><p>Render the public help landing article. Use this as the general support entry point for artists and tenant admins.</p><pre><code>curl -i https://artsfol.io/help</code></pre></article>
    <article><h3>GET /help/{article}</h3><p>Render a specific help article, such as branding, artworks, events, directory, stats, audit, or developer. Developer reference is login-gated.</p><pre><code>curl -i https://artsfol.io/help/branding</code></pre></article>
    <article><h3>GET /developer</h3><p>Compatibility route for the developer reference. It requires login and should redirect anonymous users to the login flow.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/developer</code></pre></article>
</div>

<h2>Platform admin routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /platform/admin</h3><p>Open the platform admin dashboard. This is for global ArtsFolio operations, not tenant-site editing.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin</code></pre></article>
    <article><h3>GET /platform/admin/platform-settings</h3><p>Edit global platform settings such as platform branding, copyright, directory behavior, OAuth configuration notes, and directory thumbnail sizing.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/platform-settings</code></pre></article>
    <article><h3>POST /platform/admin/platform-settings</h3><p>Save platform settings. Use the browser form so CSRF and audit behavior remain intact.</p><pre><code>curl -i -X POST https://artsfol.io/platform/admin/platform-settings \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'platform_footer_copyright_html=© {year} ArtsFolio'</code></pre></article>
    <article><h3>GET /platform/admin/domains</h3><p>Review custom-domain status, DNS verification state, and related jobs. Use this to troubleshoot tenant custom domains.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/domains</code></pre></article>
    <article><h3>POST /platform/admin/domains/action</h3><p>Run domain actions such as DNS verification. The response returns to the domain admin screen with status messaging.</p><pre><code>curl -i -X POST https://artsfol.io/platform/admin/domains/action \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'domain_id=123' \
  -d 'action=verify_dns'</code></pre></article>
    <article><h3>GET /platform/admin/stats</h3><p>View platform analytics, including aggregate day/hour charts and location/IP drill-downs when analytics data is present.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/stats</code></pre></article>
    <article><h3>GET /platform/admin/audit-log</h3><p>Review platform audit events for security and administrative changes.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/audit-log</code></pre></article>
    <article><h3>GET /platform/admin/audit-log.csv</h3><p>Export platform audit entries as CSV for review or archival.</p><pre><code>curl -OJ -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/audit-log.csv</code></pre></article>
</div>

<h2>Tenant public routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /</h3><p>Render the tenant public homepage on the tenant hostname. The platform tenant resolver chooses the tenant from the host.</p><pre><code>curl -i https://bxiie.com/</code></pre></article>
    <article><h3>GET /portfolio</h3><p>Render the tenant portfolio page. Section filters may be applied by query string when portfolio sections exist.</p><pre><code>curl -i 'https://bxiie.com/portfolio?section=sculpture'</code></pre></article>
    <article><h3>GET /artwork/{slug}</h3><p>Render a public artwork detail page by artwork slug. Use this for collector, press, and directory links to specific works.</p><pre><code>curl -i https://bxiie.com/artwork/example-work</code></pre></article>
    <article><h3>GET /about</h3><p>Render the tenant about page, including configured copy and exhibition/event content when present.</p><pre><code>curl -i https://bxiie.com/about</code></pre></article>
    <article><h3>GET /contact</h3><p>Render the tenant contact page and contact form.</p><pre><code>curl -i https://bxiie.com/contact</code></pre></article>
    <article><h3>POST /contact</h3><p>Submit a tenant contact message. The browser path is CSRF and rate-limit protected, and should be used through the rendered form.</p><pre><code>curl -i -X POST https://bxiie.com/contact \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'name=Collector Name' \
  -d 'email=collector@example.com' \
  -d 'subject=Inquiry' \
  -d 'message=I am interested in this work.'</code></pre></article>
    <article><h3>POST /signup</h3><p>Submit a tenant mailing-list signup when that form is exposed by the tenant site. The request is tenant-scoped by hostname.</p><pre><code>curl -i -X POST https://bxiie.com/signup \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'email=collector@example.com'</code></pre></article>
</div>

<h2>Tenant admin routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /admin</h3><p>Open the tenant admin dashboard for the current tenant hostname. Use this for site/content work, not global platform operations.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin</code></pre></article>
    <article><h3>GET /admin/settings</h3><p>Edit tenant branding, public labels, CSS, page copy settings, slugs, colors, and public-site options.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/settings</code></pre></article>
    <article><h3>GET /admin/directory</h3><p>Configure tenant discovery opt-in, directory summary, and featured directory thumbnail selection.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/directory</code></pre></article>
    <article><h3>POST /admin/directory</h3><p>Save tenant directory settings. Use the browser form to preserve CSRF validation and audit logging.</p><pre><code>curl -i -X POST https://bxiie.com/admin/directory \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'platform_directory_opt_in=1' \
  -d 'platform_directory_summary=Contemporary geometric work.'</code></pre></article>
    <article><h3>GET /admin/artworks</h3><p>Manage tenant artwork inventory, image uploads, metadata, publish status, and portfolio assignments.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/artworks</code></pre></article>
    <article><h3>GET /admin/portfolio-sections</h3><p>Manage portfolio sections and ordering. Use this before assigning or manually ordering artworks in public groupings.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/portfolio-sections</code></pre></article>
    <article><h3>GET /admin/events</h3><p>Manage exhibitions, fairs, talks, residencies, and other date-based public history.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/events</code></pre></article>
    <article><h3>GET /admin/stats</h3><p>Review tenant-scoped analytics and content engagement for the current tenant.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/stats</code></pre></article>
    <article><h3>GET /admin/audit-log</h3><p>Review tenant-scoped administrative and security events.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/audit-log</code></pre></article>
</div>
HTML;

        return Response::html($this->layout('Developer reference', $body, 'developer', $currentUser));
    }

    private function layout(string $title, string $body, string $active, ?array $currentUser): string
    {
        $safeTitle = self::escape($title);
        $nav = $this->nav($active, $currentUser !== null);
        $platformAdminLink = \App\Http\View\PlatformChrome::platformAdminLink();
        $auth = $currentUser
            ? '<form method="post" action="/logout" class="inline-form"><button type="submit">Log out</button></form>'
            : '<a href="/login">Sign in</a>';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | ArtsFolio Help</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css">
</head>
<body class="tenant-admin-page platform-help-page">
<header class="platform-header platform-help-header">
    <a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    <nav><a href="/">Home</a><a href="/pricing">Pricing</a><a href="/directory">Artists</a>{$platformAdminLink}{$auth}</nav>
</header>
<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Help navigation">
        <div class="tenant-admin-sidebar-title"><strong>Help</strong><span>Guides and reference</span></div>
        {$nav}
    </aside>
    <main class="tenant-admin-main">
        <section class="tenant-admin-panel help-article"><h1>{$safeTitle}</h1>{$body}</section>
    </main>
</div>
<footer class="platform-footer"><span>{\App\Http\View\PlatformChrome::copyrightLine()}</span></footer>
</body>
</html>
HTML;
    }

    private function nav(string $active, bool $loggedIn): string
    {
        $items = [
            'getting-started' => ['/help', 'Getting started'],
            'branding' => ['/help/branding', 'Branding and CSS'],
            'artworks' => ['/help/artworks', 'Artwork management'],
            'events' => ['/help/events', 'Events'],
            'directory' => ['/help/directory', 'Artist directory'],
            'stats' => ['/help/stats', 'Stats'],
            'audit' => ['/help/audit', 'Audit log'],
            'developer' => ['/help/developer', $loggedIn ? 'Developer reference' : 'Developer reference 🔒'],
        ];

        $html = '<nav>';
        foreach ($items as $key => [$href, $label]) {
            $class = $active === $key ? ' class="active"' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }
        $html .= '</nav><p class="admin-muted"><a href="/">← Back to ArtsFolio</a></p>';

        return $html;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
