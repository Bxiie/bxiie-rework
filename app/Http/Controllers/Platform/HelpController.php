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
        if (!$currentUser) {
            return new Response('', 302, ['Location' => '/login?next=/help/developer']);
        }

        $body = <<<HTML
<p class="admin-muted">Developer information is visible only after login. It is written as a practical route map for a junior developer implementing against ArtsFolio.</p>
<h2>Browser authentication</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/login</code></td><td>Render branded login.</td></tr>
<tr><td>POST</td><td><code>/login</code></td><td>Submit email/password login from the branded form.</td></tr>
<tr><td>POST</td><td><code>/login/password</code></td><td>Backward-compatible password-login endpoint.</td></tr>
<tr><td>POST</td><td><code>/logout</code></td><td>Clear browser authorization and return to login.</td></tr>
</tbody></table>
<h2>Platform public routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/</code></td><td>ArtsFolio landing page.</td></tr>
<tr><td>GET</td><td><code>/pricing</code></td><td>Plan comparison and signup call to action.</td></tr>
<tr><td>GET</td><td><code>/signup</code></td><td>Tenant signup form.</td></tr>
<tr><td>POST</td><td><code>/signup</code></td><td>Create the tenant and initial user.</td></tr>
<tr><td>GET</td><td><code>/directory</code></td><td>Opted-in artist directory.</td></tr>
<tr><td>GET</td><td><code>/help</code></td><td>Help landing article.</td></tr>
<tr><td>GET</td><td><code>/help/{article}</code></td><td>Help article or developer reference.</td></tr>
</tbody></table>
<h2>Tenant routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/admin</code></td><td>Tenant dashboard.</td></tr>
<tr><td>GET</td><td><code>/admin/settings</code></td><td>Tenant settings and CSS.</td></tr>
<tr><td>GET</td><td><code>/admin/directory</code></td><td>Tenant directory opt-in.</td></tr>
<tr><td>POST</td><td><code>/admin/directory</code></td><td>Save tenant directory opt-in.</td></tr>
<tr><td>GET</td><td><code>/admin/artworks</code></td><td>Artwork inventory.</td></tr>
<tr><td>GET</td><td><code>/admin/events</code></td><td>Events and exhibitions.</td></tr>
<tr><td>GET</td><td><code>/admin/stats</code></td><td>Tenant stats.</td></tr>
<tr><td>GET</td><td><code>/admin/audit-log</code></td><td>Tenant audit entries.</td></tr>
</tbody></table>
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
