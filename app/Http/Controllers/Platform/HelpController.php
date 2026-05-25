<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;

/**
 * Renders the combined Help and Developer section.
 *
 * Public help articles are available to anyone. Developer implementation
 * details are gated to logged-in users because they expose operational route
 * shape, implementation assumptions, and integration usage notes.
 */
final class HelpController
{
    /** @var array<string, array{title:string, audience:string, body:string}> */
    private array $articles;

    public function __construct()
    {
        $this->articles = $this->buildArticles();
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        return $this->article($request, 'getting-started', $currentUser);
    }

    public function article(Request $request, string $slug, ?array $currentUser): Response
    {
        if ($slug === 'developer') {
            return $this->developer($request, $currentUser);
        }

        if (!isset($this->articles[$slug])) {
            return Response::html($this->layout(
                title: 'Help article not found',
                active: '',
                body: '<p class="admin-error">That help article does not exist.</p><p><a class="admin-button" href="/help">Back to help</a></p>',
                currentUser: $currentUser,
            ), 404);
        }

        $article = $this->articles[$slug];

        return Response::html($this->layout(
            title: $article['title'],
            active: $slug,
            body: $article['body'],
            currentUser: $currentUser,
        ));
    }

    public function developer(Request $request, ?array $currentUser): Response
    {
        if (!$currentUser) {
            return new Response('', 302, ['Location' => '/login?next=/help/developer']);
        }

        $body = <<<HTML
<p class="admin-muted">This page is intentionally available only after login. It is written for a junior developer who needs to implement calls against ArtsFolio without spelunking through the router.</p>

<section class="help-card-grid">
    <article><h2>Authentication model</h2><p>Browser users authenticate through <code>GET /login</code> and <code>POST /login</code>. API clients use OAuth-style bearer tokens on <code>Authorization: Bearer TOKEN</code>.</p></article>
    <article><h2>Tenant resolution</h2><p>Tenant public/admin routes are selected by the host name. Platform routes live on <code>artsfol.io</code>. Tenant custom domains and subdomains resolve before route dispatch.</p></article>
    <article><h2>CSRF</h2><p>Browser forms include <code>csrf_token</code>. API bearer-token routes should not rely on browser cookies.</p></article>
</section>

<h2>Public platform routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/</code></td><td>Platform landing page.</td></tr>
<tr><td>GET</td><td><code>/pricing</code></td><td>Plan comparison and signup CTA.</td></tr>
<tr><td>GET</td><td><code>/signup</code></td><td>Tenant signup form.</td></tr>
<tr><td>POST</td><td><code>/signup</code></td><td>Create user, tenant, default domain, and initial membership.</td></tr>
<tr><td>GET</td><td><code>/directory</code></td><td>Public artist directory when enabled by platform settings.</td></tr>
<tr><td>GET</td><td><code>/help/{article}</code></td><td>Help article shell with sidebar navigation.</td></tr>
</tbody></table>

<h2>Browser authentication routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/login</code></td><td>Render branded sign-in form.</td></tr>
<tr><td>POST</td><td><code>/login</code></td><td>Submit email/password login. Use <code>keep_me_logged_in=1</code> for persistent auth.</td></tr>
<tr><td>POST</td><td><code>/login/password</code></td><td>Backward-compatible password login route.</td></tr>
<tr><td>POST</td><td><code>/logout</code></td><td>Clear browser session.</td></tr>
<tr><td>GET</td><td><code>/auth/google</code></td><td>Start Google login or tenant creation flow when configured.</td></tr>
<tr><td>GET</td><td><code>/auth/facebook</code></td><td>Start Facebook login or tenant creation flow when configured.</td></tr>
</tbody></table>

<h2>Platform admin routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/admin</code></td><td>Platform admin dashboard.</td></tr>
<tr><td>GET</td><td><code>/admin/tenants</code></td><td>Tenant inventory.</td></tr>
<tr><td>GET</td><td><code>/admin/domains</code></td><td>Custom domain verification and artifacts.</td></tr>
<tr><td>GET</td><td><code>/admin/jobs</code></td><td>Background job queue.</td></tr>
<tr><td>GET</td><td><code>/admin/workers</code></td><td>Worker heartbeat status.</td></tr>
<tr><td>GET</td><td><code>/admin/email-outbox</code></td><td>Queued and sent platform email.</td></tr>
<tr><td>GET</td><td><code>/admin/audit-log</code></td><td>Platform audit entries.</td></tr>
<tr><td>GET</td><td><code>/admin/platform-settings</code></td><td>Platform name, support email, directory toggle, custom CSS, auth duration.</td></tr>
</tbody></table>

<h2>Tenant admin routes</h2>
<table class="admin-table"><thead><tr><th>Method</th><th>Route</th><th>Use</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/admin</code></td><td>Tenant dashboard.</td></tr>
<tr><td>GET</td><td><code>/admin/settings</code></td><td>Tenant branding, tabs, CSS, contact settings, and site behavior.</td></tr>
<tr><td>GET</td><td><code>/admin/artworks</code></td><td>Artwork inventory and edit links.</td></tr>
<tr><td>GET</td><td><code>/admin/events</code></td><td>Exhibition and event management.</td></tr>
<tr><td>GET</td><td><code>/admin/stats</code></td><td>Tenant analytics.</td></tr>
<tr><td>GET</td><td><code>/admin/audit-log</code></td><td>Tenant-scoped audit entries.</td></tr>
<tr><td>GET</td><td><code>/api/me</code></td><td>Bearer-token identity and tenant access test endpoint.</td></tr>
</tbody></table>
HTML;

        return Response::html($this->layout(
            title: 'Developer reference',
            active: 'developer',
            body: $body,
            currentUser: $currentUser,
        ));
    }

    private function layout(string $title, string $active, string $body, ?array $currentUser): string
    {
        $safeTitle = self::escape($title);
        $nav = $this->sidebar($active, $currentUser !== null);
        $authLink = $currentUser ? '<form method="post" action="/logout"><button type="submit">Log out</button></form>' : '<a class="admin-button" href="/login">Sign in</a>';

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
<body class="tenant-admin-page platform-help-page" style="--tenant-topbar-bg:#f7f1e8;--tenant-topbar-text:#151515;">
<header class="site-header tenant-admin-public-header platform-help-header">
    <a class="platform-help-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    <nav><a href="/">Platform home</a><a href="/pricing">Pricing</a><a href="/directory">Artists</a><a href="/admin">Admin</a>{$authLink}</nav>
</header>
<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Help navigation">
        <div class="tenant-admin-sidebar-title"><strong>Help</strong><span>Guides + developer reference</span></div>
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

    private function sidebar(string $active, bool $isLoggedIn): string
    {
        $items = [
            'getting-started' => ['/help/getting-started', 'Getting started'],
            'branding' => ['/help/branding', 'Branding and CSS'],
            'artworks' => ['/help/artworks', 'Artwork management'],
            'events' => ['/help/events', 'Events and exhibitions'],
            'directory' => ['/help/directory', 'Artist directory'],
            'stats' => ['/help/stats', 'Stats'],
            'audit' => ['/help/audit', 'Audit log'],
            'developer' => ['/help/developer', 'Developer reference'],
        ];

        $html = '<nav>';
        foreach ($items as $key => [$href, $label]) {
            if ($key === 'developer' && !$isLoggedIn) {
                $label .= ' 🔒';
            }
            $class = $active === $key ? ' class="active"' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }

    /** @return array<string, array{title:string, audience:string, body:string}> */
    private function buildArticles(): array
    {
        return [
            'getting-started' => ['title' => 'Getting started', 'audience' => 'all', 'body' => '<p>Start by creating or logging into an account, then create a tenant site from <a href="/signup">signup</a>. After creation, use the tenant admin dashboard to upload artwork, edit content, and publish public pages.</p><ol><li>Set the site title and artist name.</li><li>Upload at least one artwork image.</li><li>Create portfolio sections.</li><li>Review contact and signup forms.</li><li>Open the public site in a private window before announcing it.</li></ol>'],
            'branding' => ['title' => 'Branding and CSS', 'audience' => 'all', 'body' => '<p>Tenant admins manage tenant colors, tab labels, page copy, images, and tenant CSS from tenant settings. Platform admins manage global platform CSS from Platform Settings.</p><p>Use CSS for controlled adjustments only. Keep it readable and comment non-obvious rules.</p>'],
            'artworks' => ['title' => 'Artwork management', 'audience' => 'all', 'body' => '<p>Use Artworks to upload images, set title, medium, dimensions, year, availability, public status, and portfolio sections. Public artwork should have meaningful titles and clean images.</p>'],
            'events' => ['title' => 'Events and exhibitions', 'audience' => 'all', 'body' => '<p>Use Events to maintain exhibitions, fairs, open studios, and public appearances. Sort order controls how featured events appear. Filters help separate current, past, draft, and archived entries.</p>'],
            'directory' => ['title' => 'Artist directory', 'audience' => 'all', 'body' => '<p>The directory is controlled at two levels. Platform admins can enable or disable the directory globally. Tenant admins opt each tenant into public directory visibility from Discovery settings.</p>'],
            'stats' => ['title' => 'Stats', 'audience' => 'all', 'body' => '<p>Stats pages summarize recorded analytics events. Tenant stats are scoped to the current tenant. Platform stats aggregate across the platform when the platform route is available.</p><p>If stats are empty, verify that public page requests are writing analytics events and that the selected date range includes traffic.</p>'],
            'audit' => ['title' => 'Audit log', 'audience' => 'all', 'body' => '<p>Audit logs record important administrative actions such as settings changes, domain actions, job actions, authentication events, and selected tenant-admin edits. If an expected action is absent, the controller probably needs an explicit audit write.</p>'],
        ];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
