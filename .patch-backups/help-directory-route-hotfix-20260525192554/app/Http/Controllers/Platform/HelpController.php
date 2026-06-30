<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;

/**
 * Combined public Help and logged-in Developer reference.
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
                'body' => '<p>Start by signing in, creating or opening a tenant, setting the public artist name, and adding a first artwork record. Then publish home, about, contact, and portfolio content from tenant admin.</p><ol class="flow-list compact"><li><strong>Sign in</strong><span>Use email/password, Google, or Facebook when configured.</span></li><li><strong>Open admin</strong><span>Use the Admin link after login.</span></li><li><strong>Brand the site</strong><span>Set artist name, site title, navigation labels, colors, logo, and CSS.</span></li><li><strong>Add work</strong><span>Upload artworks, assign sections, and publish selected records.</span></li></ol>',
            ],
            'branding' => [
                'title' => 'Branding and CSS',
                'body' => '<p>Tenant admins manage artist-site colors, images, page labels, CSS, and navigation from tenant Settings. Platform admins manage ArtsFolio-wide branding and platform CSS from Platform Settings.</p>',
            ],
            'artworks' => [
                'title' => 'Artwork management',
                'body' => '<p>Use Artworks to upload images, add titles, medium, dimensions, sale status, and public notes. Assign each artwork to one or more portfolio sections so public pages remain navigable.</p>',
            ],
            'events' => [
                'title' => 'Events and exhibitions',
                'body' => '<p>Use Events for exhibitions, fairs, talks, studio visits, and other date-based history. Admin filtering and ordering help keep long histories readable.</p>',
            ],
            'directory' => [
                'title' => 'Artist directory',
                'body' => '<p>The public directory requires two switches: platform admins must enable the directory globally, and tenant admins must opt their tenant into discovery. Tenant opt-in lives under tenant Discovery settings.</p>',
            ],
            'stats' => [
                'title' => 'Stats',
                'body' => '<p>Tenant stats show tenant traffic. Platform stats show platform-level traffic and operational totals. If stats are empty, verify that analytics events are being written and that the route is being reached through the correct host.</p>',
            ],
            'audit' => [
                'title' => 'Audit log',
                'body' => '<p>Audit entries record security and administrative changes. Tenant audit pages should show tenant-scoped admin actions. Platform audit pages show platform administration and authentication events.</p>',
            ],
        ];
    }

    public function index(Request $request, ?array $currentUser = null): Response
    {
        return $this->article($request, 'getting-started', $currentUser);
    }

    public function article(Request $request, string|array $slug = 'getting-started', ?array $currentUser = null): Response
    {
        if (is_array($slug)) {
            $slug = (string) ($slug['article'] ?? 'getting-started');
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
<h2>Browser auth</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/login</code></td><td>Render branded login.</td></tr>
<tr><td>POST</td><td><code>/login</code></td><td>Submit email/password login.</td></tr>
<tr><td>POST</td><td><code>/login/password</code></td><td>Backward-compatible password login endpoint.</td></tr>
<tr><td>POST</td><td><code>/logout</code></td><td>Clear the browser session and redirect to login.</td></tr>
</tbody></table>
<h2>Platform routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/</code></td><td>Platform landing page.</td></tr>
<tr><td>GET</td><td><code>/pricing</code></td><td>Professional plan comparison.</td></tr>
<tr><td>GET</td><td><code>/signup</code></td><td>Tenant signup form.</td></tr>
<tr><td>POST</td><td><code>/signup</code></td><td>Create tenant/user.</td></tr>
<tr><td>GET</td><td><code>/directory</code></td><td>Artist directory.</td></tr>
<tr><td>GET</td><td><code>/help/{article}</code></td><td>Help and developer article shell.</td></tr>
</tbody></table>
<h2>Platform admin routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/admin</code></td><td>Admin dashboard cards.</td></tr>
<tr><td>GET</td><td><code>/admin/platform-settings</code></td><td>Platform name, support email, CSS, directory, auth duration.</td></tr>
<tr><td>GET</td><td><code>/admin/stats</code></td><td>Platform analytics.</td></tr>
<tr><td>GET</td><td><code>/admin/audit-log</code></td><td>Platform audit entries.</td></tr>
<tr><td>GET</td><td><code>/admin/tenants</code></td><td>Tenant inventory.</td></tr>
<tr><td>GET</td><td><code>/admin/domains</code></td><td>Custom domains.</td></tr>
<tr><td>GET</td><td><code>/admin/jobs</code></td><td>Background jobs.</td></tr>
</tbody></table>
<h2>Tenant admin routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/admin</code></td><td>Tenant dashboard.</td></tr>
<tr><td>GET</td><td><code>/admin/settings</code></td><td>Tenant settings and CSS.</td></tr>
<tr><td>GET</td><td><code>/admin/artworks</code></td><td>Artwork inventory.</td></tr>
<tr><td>GET</td><td><code>/admin/events</code></td><td>Events and exhibitions.</td></tr>
<tr><td>GET</td><td><code>/admin/stats</code></td><td>Tenant stats.</td></tr>
<tr><td>GET</td><td><code>/admin/audit-log</code></td><td>Tenant audit entries.</td></tr>
<tr><td>GET</td><td><code>/api/me</code></td><td>Bearer-token identity check.</td></tr>
</tbody></table>
HTML;

        return Response::html($this->layout('Developer reference', $body, 'developer', $currentUser));
    }

    private function layout(string $title, string $body, string $active, ?array $currentUser): string
    {
        $safeTitle = self::escape($title);
        $nav = $this->nav($active, $currentUser !== null);
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
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout">
</head>
<body class="tenant-admin-page platform-help-page">
<header class="platform-header platform-help-header">
    <a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    <nav><a href="/">Home</a><a href="/pricing">Pricing</a><a href="/directory">Artists</a><a href="/admin">Admin</a>{$auth}</nav>
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
