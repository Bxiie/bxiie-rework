<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;

/**
 * Professional public pricing page for ArtsFolio plans.
 */
final class PricingController
{
    public function index(Request $request): Response
    {
        $body = <<<HTML
<section class="platform-hero pricing-hero compact">
    <div>
        <p class="eyebrow">Pricing</p>
        <h1>Choose the ArtsFolio plan that matches your practice.</h1>
        <p class="hero-copy">Start with a clean artist portfolio, then grow into analytics, collector workflows, custom domains, and larger operating needs without rebuilding the site from scratch.</p>
        <div class="hero-actions"><a class="button primary" href="/signup">Start now</a><a class="button secondary" href="/contact">Ask about setup</a></div>
    </div>
</section>
<section class="pricing-grid professional-pricing">
    <article class="pricing-card"><p class="eyebrow">Starter</p><h2>Free</h2><p class="price">$0</p><p>For evaluation, students, and artists publishing a compact first portfolio.</p><ul><li>ArtsFolio subdomain</li><li>Core portfolio pages</li><li>Basic contact form</li><li>Directory opt-in when enabled</li><li>Limited artwork inventory</li></ul><a class="button secondary" href="/signup">Start Free</a></article>
    <article class="pricing-card featured"><p class="eyebrow">Most working artists</p><h2>Studio</h2><p class="price">Low monthly</p><p>For artists who need a polished site with practical admin tools and room to grow.</p><ul><li>Expanded artwork inventory</li><li>Portfolio sections</li><li>Events and exhibition history</li><li>Email signup management</li><li>Contact-message admin</li><li>Tenant CSS editor</li><li>Tenant analytics and audit log</li></ul><a class="button primary" href="/signup">Choose Studio</a></article>
    <article class="pricing-card"><p class="eyebrow">Professional presence</p><h2>Custom Domain</h2><p class="price">Higher tier</p><p>For artists who need their own domain and a more formal collector-facing presentation.</p><ul><li>Everything in Studio</li><li>Custom domain workflow</li><li>DNS verification support</li><li>Advanced branding controls</li><li>Priority setup assistance</li></ul><a class="button secondary" href="/contact">Discuss domain setup</a></article>
    <article class="pricing-card"><p class="eyebrow">Groups</p><h2>Collective</h2><p class="price">By scope</p><p>For galleries, artist groups, estates, and organizations managing more complex collections.</p><ul><li>Multi-user administration</li><li>Larger artwork capacity</li><li>Migration planning</li><li>Operational support</li><li>Custom onboarding</li></ul><a class="button secondary" href="/contact">Contact ArtsFolio</a></article>
</section>
<section class="platform-section comparison-section"><h2>Plan comparison</h2><table class="admin-table"><thead><tr><th>Feature</th><th>Starter</th><th>Studio</th><th>Custom Domain</th><th>Collective</th></tr></thead><tbody><tr><td>ArtsFolio subdomain</td><td>Included</td><td>Included</td><td>Included</td><td>Included</td></tr><tr><td>Custom domain</td><td>Not included</td><td>Optional upgrade</td><td>Included</td><td>Included by scope</td></tr><tr><td>Portfolio sections</td><td>Basic</td><td>Full</td><td>Full</td><td>Full</td></tr><tr><td>Analytics</td><td>Basic</td><td>Tenant stats</td><td>Tenant stats</td><td>Tenant and operational reporting</td></tr><tr><td>Support</td><td>Self-service</td><td>Standard</td><td>Priority setup</td><td>Custom</td></tr></tbody></table></section>
HTML;

        return Response::html($this->layout('Pricing | ArtsFolio', $body));
    }

    private function layout(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ArtsFolio pricing for artist portfolio and sales platform plans.">
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout">
</head>
<body>
<header class="platform-header"><a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a class="active" href="/pricing">Pricing</a><a href="/directory">Artists</a><a href="/help">Help</a><a href="/login">Sign in</a></nav></header>
<main>{$body}</main>
<footer class="platform-footer"><span>© ArtsFolio</span><nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer>
</body>
</html>
HTML;
    }
}

// End of file.
