<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;

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
                'body' => <<<'HTML'
<p>ArtsFolio turns an artist site into a managed portfolio, contact, events, sales, analytics, and discovery workspace. Start by signing in, opening tenant admin, completing the setup tour, and publishing a small complete site.</p><ol class="flow-list compact"><li><strong>Open admin.</strong><span>Use <code>/admin</code> on the tenant domain.</span></li><li><strong>Run the setup tour.</strong><span>Follow the recommended setup order.</span></li><li><strong>Set branding and content.</strong><span>Confirm identity, colors, About, Contact, and navigation.</span></li><li><strong>Add artwork and sections.</strong><span>Create sections, upload work, and curate the public site.</span></li><li><strong>Test launch paths.</strong><span>Check contact, email signup, cart, stats, and mobile layout.</span></li></ol><p><a class="button" href="/help/new-admin-tour">Open the new-admin setup tour</a> <a class="button" href="/help/tenant-admin-functions">Open the tenant function index</a></p>
HTML,
            ],
            'new-admin-tour' => [
                'title' => 'New admin setup tour',
                'body' => <<<'HTML'
<p>This tour is the recommended path for turning a new tenant into a credible public site.</p><ol class="flow-list compact"><li><strong>Dashboard.</strong><span>Open <code>/admin</code> and review activity, messages, sales, and shortcuts.</span></li><li><strong>Settings.</strong><span>Set site name, metadata, labels, palette, typography, logo, watermark defaults, directory summary, and visibility.</span></li><li><strong>Content.</strong><span>Add About copy, Contact copy, contact instructions, and selected site images.</span></li><li><strong>Portfolio Sections.</strong><span>Create public groupings and sort them into visitor order.</span></li><li><strong>Upload Artwork.</strong><span>Use <code>/admin/artwork/upload</code> and fill image, title, year, medium, dimensions, description, notes, sale status, price, inventory, and sections.</span></li><li><strong>Curation.</strong><span>Choose featured work and public ordering.</span></li><li><strong>Events.</strong><span>Add exhibitions, openings, residencies, talks, fairs, dates, locations, and links.</span></li><li><strong>Engagement.</strong><span>Test contact and email signup, then review Messages and Email Signups.</span></li><li><strong>Sales.</strong><span>Prepare prices, inventory, checkout, orders, analytics, and refund guardrails.</span></li><li><strong>Users, Domains, Billing.</strong><span>Invite helpers, verify DNS, and confirm plan state.</span></li><li><strong>Launch verification.</strong><span>Review Stats, Audit Log, Tenant Routes, public pages, phone layout, contact, cart, and directory listing.</span></li></ol><p><a class="button" href="/help/training-videos">View proposed training videos</a></p>
HTML,
            ],
            'tenant-admin-functions' => [
                'title' => 'Tenant admin function index',
                'body' => <<<'HTML'
<p>This index lists every tenant admin function and links each one to the relevant help topic.</p><ul class="link-list"><li><strong>Dashboard</strong> <code>/admin</code>: setup overview, recent messages, sales status, and operational shortcuts. See <a href="/help/new-admin-tour">new-admin setup tour</a>.</li><li><strong>Upload Artwork</strong> <code>/admin/artwork/upload</code>: direct upload entry point. See <a href="/help/artworks">artwork management</a>.</li><li><strong>Settings</strong> <code>/admin/settings</code>: identity, branding, labels, palette, typography, visibility, and tenant configuration. See <a href="/help/branding">branding</a>.</li><li><strong>Content</strong> <code>/admin/content</code>: About, Contact, site images, and public static content. See <a href="/help/branding">branding</a>.</li><li><strong>Artworks</strong> <code>/admin/artworks</code>: grid, filters, edit workflow, publication, image metadata, sale fields, inventory, and section placement. See <a href="/help/artworks">artwork management</a>.</li><li><strong>Curation</strong> <code>/admin/curation</code>: homepage and portfolio ordering. See <a href="/help/artworks">artwork management</a>.</li><li><strong>Portfolio Sections</strong> <code>/admin/portfolio-sections</code>: create, edit, sort, and publish artwork groups. See <a href="/help/artworks">artwork management</a>.</li><li><strong>Events</strong> <code>/admin/events</code>: exhibitions, fairs, talks, residencies, public dates, locations, and history. See <a href="/help/events">events</a>.</li><li><strong>Messages</strong> <code>/admin/contact-messages</code>: review visitor contact submissions. See <a href="/help/messages-email">messages and email signups</a>.</li><li><strong>Email Signups</strong> <code>/admin/email-signups</code>: review interested visitor email addresses. See <a href="/help/messages-email">messages and email signups</a>.</li><li><strong>Domains</strong> <code>/admin/domains</code>: custom hostname and DNS verification workflow. See <a href="/help/users-domains-billing">users, domains, and billing</a>.</li><li><strong>Billing</strong> <code>/admin/billing</code>: plan, billing status, checkout requirements, and subscription state. See <a href="/help/users-domains-billing">users, domains, and billing</a>.</li><li><strong>Sales</strong> <code>/admin/sales</code>: orders, payment status, workflow status, customer information, Stripe identifiers, and refunds. See <a href="/help/sales">sales and refunds</a>.</li><li><strong>Sales Analytics</strong> <code>/admin/sales/analytics</code>: order totals, conversion clues, and reporting. See <a href="/help/sales">sales and refunds</a>.</li><li><strong>Users</strong> <code>/admin/users</code>: invite, review, and remove tenant admins and helpers. See <a href="/help/users-domains-billing">users, domains, and billing</a>.</li><li><strong>Stats</strong> <code>/admin/stats</code>: tenant traffic and content engagement. See <a href="/help/stats">stats</a>.</li><li><strong>Audit Log</strong> <code>/admin/audit-log</code>: login, security, and admin change records. See <a href="/help/audit">audit log</a>.</li><li><strong>Tenant Routes</strong> <code>/admin/routes</code>: host and route diagnostics. See <a href="/help/audit">audit and diagnostics</a>.</li><li><strong>Getting Started</strong> <code>/admin/getting-started</code>: tenant-local setup checklist. See <a href="/help/new-admin-tour">new-admin setup tour</a>.</li></ul>
HTML,
            ],
            'branding' => [
                'title' => 'Branding, settings, and content',
                'body' => <<<'HTML'
<p>Settings and Content control public identity. Use Settings for site name, metadata, labels, palette, typography, logo, watermark defaults, directory text, custom CSS, and visibility. Use Content for About copy, Contact copy, selected site images, and static page details. Save one group of changes at a time and review the public site on desktop and mobile.</p>
HTML,
            ],
            'artworks' => [
                'title' => 'Artwork, sections, and curation',
                'body' => <<<'HTML'
<p>Use Upload Artwork for new records. Add image, title, year, medium, dimensions, description, publication status, sale status, price, inventory, and section placement.</p><p>Use the Artworks grid to search, filter, sort, edit, publish, unpublish, price, and place work. Use Portfolio Sections for public groups and Curation for featured work and ordering.</p>
HTML,
            ],
            'events' => [
                'title' => 'Events and exhibitions',
                'body' => <<<'HTML'
<p>Events record exhibitions, fairs, talks, residencies, open studios, installations, deadlines, and dated activity. Add title, dates, location, optional URL, description, and public status.</p>
HTML,
            ],
            'sales' => [
                'title' => 'Sales, checkout, orders, analytics, and refunds',
                'body' => <<<'HTML'
<p>Sales connects artwork availability to carts, Stripe checkout, order review, fulfillment workflow, analytics, and refunds.</p><p>Before selling, confirm publication, price, inventory, one-off behavior, shipping, and Stripe setup. In Sales, review order number, payment status, workflow status, totals, customer email, Stripe identifiers, and refund eligibility. Failed refund messages are stop signs. Verify Stripe before any further refund attempt.</p><p>Sales Analytics summarizes order volume, revenue, and conversion clues.</p>
HTML,
            ],
            'messages-email' => [
                'title' => 'Messages and email signups',
                'body' => <<<'HTML'
<p>Messages shows contact form submissions. Email Signups shows visitors who requested updates. Review sender, body, timestamp, and context before replying or exporting addresses.</p>
HTML,
            ],
            'users-domains-billing' => [
                'title' => 'Users, domains, and billing',
                'body' => <<<'HTML'
<p>Users manages tenant admins and helpers. Give the minimum role needed and remove stale access promptly.</p><p>Domains tracks custom hostnames, DNS verification, certificate state, and primary-domain behavior. Billing shows plan state, payment requirements, limits, and account standing.</p>
HTML,
            ],
            'directory' => [
                'title' => 'Artist directory',
                'body' => <<<'HTML'
<p>The directory has two gates: platform-wide enablement and tenant opt-in. Before opting in, confirm the public site has a strong image, useful summary, current contact path, and published artwork.</p>
HTML,
            ],
            'stats' => [
                'title' => 'Stats',
                'body' => <<<'HTML'
<p>Stats show tenant traffic and engagement. Empty stats usually require checking host, analytics writes, bot filtering, and date ranges.</p>
HTML,
            ],
            'audit' => [
                'title' => 'Audit log and tenant diagnostics',
                'body' => <<<'HTML'
<p>Audit Log records login, security, and administrative changes. Tenant Routes helps diagnose host and route behavior when a domain reaches the wrong page or behaves differently across environments.</p>
HTML,
            ],
            'training-videos' => [
                'title' => 'Training video directory',
                'body' => <<<'HTML'
<p>This directory lists proposed ArtsFolio tenant-admin training videos. Video links will be added later after recording and publishing.</p><ul class="link-list"><li><strong>01. Tenant admin orientation.</strong> Dashboard, navigation, help, tour, and safe first steps. <span>Video link pending.</span></li><li><strong>02. Site identity, branding, and content.</strong> Settings, Content, logo, palette, About, and Contact. <span>Video link pending.</span></li><li><strong>03. Artwork upload and portfolio structure.</strong> Upload, edit, sections, publication, filtering, and curation. <span>Video link pending.</span></li><li><strong>04. Events and public history.</strong> Exhibitions, dates, locations, and public event hygiene. <span>Video link pending.</span></li><li><strong>05. Messages and email signups.</strong> Contact submissions, email capture, and follow-up workflow. <span>Video link pending.</span></li><li><strong>06. Sales, orders, analytics, and refunds.</strong> Sale status, Stripe checkout, order review, and refund guardrails. <span>Video link pending.</span></li><li><strong>07. Users, domains, billing, and diagnostics.</strong> Team access, custom domains, plan state, audit log, and tenant routes. <span>Video link pending.</span></li></ul><p>The full scripts are exported in <code>ArtsFolio_Tenant_Admin_Training_Video_Scripts_20260708.docx</code>.</p>
HTML,
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
        if ($this->isDeveloperResourceRequest($request) && !$currentUser) {
            return new Response('', 302, ['Location' => '/login']);
        }

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
        $canonicalNav = \App\Http\View\PlatformChrome::topNavigation('help');
        $platformCopyright = \App\Http\View\PlatformChrome::copyrightLine();
        $auth = $currentUser
            ? '<form class="plan-edit-form" method="post" action="/logout" class="inline-form"><button type="submit">Log out</button></form>'
            : '<a href="/login">Sign in</a>';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | ArtsFolio Help</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/platform.css?v=20260708-logo-aspect">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260708-logo-aspect">
</head>
<body class="tenant-admin-page platform-help-page">
<header class="platform-header platform-help-header">
    <a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    {$canonicalNav}
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
<footer class="platform-footer"><span>{$platformCopyright}</span><nav><a href="/help">Help</a><a href="/terms">Terms</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer>
</body>
</html>
HTML;
    }

    private function nav(string $active, bool $loggedIn): string
    {
        $items = [
            ['Getting started', '/help'],
            ['New admin setup tour', '/help/new-admin-tour'],
            ['Tenant function index', '/help/tenant-admin-functions'],
            ['Branding and content', '/help/branding'],
            ['Artwork and curation', '/help/artworks'],
            ['Events and exhibitions', '/help/events'],
            ['Sales and refunds', '/help/sales'],
            ['Messages and email signups', '/help/messages-email'],
            ['Users, domains, and billing', '/help/users-domains-billing'],
            ['Artist directory', '/help/directory'],
            ['Stats', '/help/stats'],
            ['Audit and diagnostics', '/help/audit'],
            ['Training videos', '/help/training-videos'],
        ];

        if ($loggedIn) {
            $items['developer'] = ['/help/developer', 'Developer reference'];
        }

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

    /**
     * Developer resources are internal operational documentation and require login.
     */
    private function isDeveloperResourceRequest(Request $request): bool
    {
        $path = $request->path();
        $topic = (string) ($_GET['topic'] ?? $_GET['article'] ?? '');

        return str_contains($path, 'developer')
            || str_contains($path, 'resources')
            || str_contains($topic, 'developer')
            || str_contains($topic, 'api')
            || str_contains($topic, 'webhook');
    }

}
