<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use PDO;
use Throwable;

/**
 * Handles public platform marketing routes that do not need database content.
 */
final class HomeController
{
    public function __construct(private readonly ?PDO $pdo = null) {}

    public function home(Request $request): Response
    {
        $body = '<h1>ArtsFolio</h1><p>Artist portfolio software with sales-ready foundations.</p>' . $this->recentSales();
        return Response::html($this->layout('ArtsFolio', $body));
    }

    public function pricing(Request $request): Response
    {
        $body = <<<HTML
<style>
.pricing-grid .pricing-card.featured, .professional-pricing .pricing-card.featured, .pricing-card.featured { color: #f8f5ed; }
.pricing-grid .pricing-card.featured li, .pricing-grid .pricing-card.featured p, .pricing-grid .pricing-card.featured small,
.professional-pricing .pricing-card.featured li, .professional-pricing .pricing-card.featured p, .professional-pricing .pricing-card.featured small,
.pricing-card.featured li, .pricing-card.featured p, .pricing-card.featured small { color: rgba(255,255,255,.9); }
.pricing-grid .pricing-card.featured .muted, .professional-pricing .pricing-card.featured .muted, .pricing-card.featured .muted { color: rgba(255,255,255,.74); }
.pricing-card .price, .professional-pricing .price { color: #1f1a14; background: rgba(255,255,255,.94); display: inline-block; padding: .25rem .55rem; border-radius: .5rem; font-weight: 800; }
</style>
<section class="platform-page-heading pricing-heading">
    <p class="eyebrow">Pricing</p>
    <h1>Plans for artists, studios, and collectives.</h1>
    <p>Start with a clean portfolio, then grow into custom domains, analytics, sales workflows, and multi-user operations when the work demands it.</p>
</section>
<section class="pricing-grid">
    <article class="pricing-card"><p class="eyebrow">Free</p><h2>Starter</h2><p class="price">$0</p><p>For testing the platform or publishing a small artist profile.</p><ul><li>1 admin user</li><li>ArtsFolio subdomain</li><li>Basic portfolio pages</li><li>Contact form</li><li>Limited artwork inventory</li></ul><a class="button secondary" href="/signup">Start free</a></article>
    <article class="pricing-card featured"><p class="eyebrow">Most artists</p><h2>Studio</h2><p class="price">Affordable monthly</p><p>For working artists who need a serious public portfolio and admin tools.</p><ul><li>3 admin users</li><li>Expanded artwork inventory</li><li>Portfolio sections and event history</li><li>Email signup and contact-message admin</li><li>Tenant CSS editor</li><li>Tenant analytics</li></ul><a class="button primary" href="/signup">Choose Studio</a></article>
    <article class="pricing-card"><p class="eyebrow">Professional</p><h2>Custom Domain</h2><p class="price">Higher tier</p><p>For artists who need their own domain and more formal collector-facing presentation.</p><ul><li>10 admin users</li><li>Everything in Studio</li><li>Custom domain support</li><li>DNS verification workflow</li><li>Priority setup help</li><li>Advanced branding controls</li></ul><a class="button secondary" href="/contact">Discuss domain setup</a></article>
    <article class="pricing-card"><p class="eyebrow">Teams</p><h2>Collective</h2><p class="price">By scope</p><p>For galleries, artist groups, estates, and multi-person practices.</p><ul><li>Unlimited admin users</li><li>Multi-user administration</li><li>Larger portfolio capacity</li><li>Operational support</li><li>Migration planning</li><li>Custom onboarding</li></ul><a class="button secondary" href="/contact">Contact ArtsFolio</a></article>
</section>
<section class="platform-section"><h2>How to choose</h2><div class="feature-grid"><article><h3>Use Free</h3><p>When you are exploring or need a very small public presence.</p></article><article><h3>Use Studio</h3><p>When the site represents your working practice and needs ongoing updates.</p></article><article><h3>Use Custom Domain</h3><p>When collectors, galleries, grants, or press should see your own domain.</p></article><article><h3>Use Collective</h3><p>When multiple people or a larger body of work need operational controls.</p></article></div></section>
HTML;

        return Response::html($this->layout('Pricing | ArtsFolio', $body));
    }

    public function signup(Request $request): Response
    {
        return Response::html($this->layout('Sign up | ArtsFolio', '<h1>Sign up</h1><p>Create a tenant workspace and start publishing your portfolio.</p><p><a class="button primary" href="/signup">Create your site</a></p>'));
    }

    public function login(Request $request): Response
    {
        return Response::html($this->layout('Login | ArtsFolio', '<h1>Login</h1><p>Use email/password, Google, or Facebook to sign in.</p><p><a class="button primary" href="/login">Sign in</a></p>'));
    }

    /**
     * Shows recent completed or in-progress sales on the public platform home.
     */
    private function recentSales(): string
    {
        if (!$this->pdo) {
            return '';
        }

        try {
            $stmt = $this->pdo->query('SELECT o.order_number, o.total_cents, o.created_at, t.name AS tenant_name, t.slug AS tenant_slug, MIN(oi.artwork_id) AS artwork_id, MIN(a.slug) AS artwork_slug, MIN(oi.title_snapshot) AS artwork_title FROM sales_orders o JOIN tenants t ON t.id = o.tenant_id JOIN sales_order_items oi ON oi.order_id = o.id LEFT JOIN artworks a ON a.id = oi.artwork_id WHERE o.payment_status IN ("checkout_pending", "paid", "payment_succeeded") GROUP BY o.id ORDER BY o.created_at DESC LIMIT 6');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return '';
        }

        if ($rows === []) {
            return '';
        }

        $cards = '';
        foreach ($rows as $row) {
            $tenantHost = 'https://' . htmlspecialchars((string) $row['tenant_slug'], ENT_QUOTES, 'UTF-8') . '.artsfol.io';
            $artworkUrl = $tenantHost . '/artwork/' . rawurlencode((string) ($row['artwork_slug'] ?? ''));
            $cards .= '<article><h3><a href="' . $artworkUrl . '">' . htmlspecialchars((string) ($row['artwork_title'] ?? 'Artwork'), ENT_QUOTES, 'UTF-8') . '</a></h3><p>Sold by <a href="' . $tenantHost . '">' . htmlspecialchars((string) $row['tenant_name'], ENT_QUOTES, 'UTF-8') . '</a></p><p>' . htmlspecialchars('$' . number_format(((int) $row['total_cents']) / 100, 2), ENT_QUOTES, 'UTF-8') . '</p></article>';
        }

        return '<section class="platform-section"><h2>Recent sales</h2><div class="feature-grid">' . $cards . '</div></section>';
    }

    private function layout(string $title, string $body): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $platformAdminLink = \App\Http\View\PlatformChrome::platformAdminLink();
        $canonicalNav = \App\Http\View\PlatformChrome::topNavigation('home');
        $platformCopyright = \App\Http\View\PlatformChrome::copyrightLine();

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/platform.css?v=20260708-logo-aspect">
    <link rel="stylesheet" href="/assets/platform-custom.css">
</head>
<body>
<header class="platform-header"><a class="platform-brand logo-brand" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>{$canonicalNav}</header>
<main>{$body}</main>
<footer class="platform-footer"><span>{$platformCopyright}</span><nav><a href="/help">Help</a><a href="/terms">Terms</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer>
<script src="/assets/platform.js" defer></script></body>
</html>
HTML;
    }
}

// End of file.
