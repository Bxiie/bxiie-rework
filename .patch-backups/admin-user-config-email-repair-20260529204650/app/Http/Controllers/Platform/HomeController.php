<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;

/**
 * Handles public platform marketing routes that do not need database content.
 */
final class HomeController
{
    public function home(Request $request): Response
    {
        return Response::html($this->layout('ArtsFolio', '<h1>ArtsFolio</h1><p>Artist portfolio software with sales-ready foundations.</p>'));
    }

    public function pricing(Request $request): Response
    {
        $body = <<<HTML
<section class="platform-page-heading pricing-heading">
    <p class="eyebrow">Pricing</p>
    <h1>Plans for artists, studios, and collectives.</h1>
    <p>Start with a clean portfolio, then grow into custom domains, analytics, sales workflows, and multi-user operations when the work demands it.</p>
</section>
<section class="pricing-grid">
    <article class="pricing-card"><p class="eyebrow">Free</p><h2>Starter</h2><p class="price">$0</p><p>For testing the platform or publishing a small artist profile.</p><ul><li>ArtsFolio subdomain</li><li>Basic portfolio pages</li><li>Contact form</li><li>Limited artwork inventory</li></ul><a class="button secondary" href="/signup">Start free</a></article>
    <article class="pricing-card featured"><p class="eyebrow">Most artists</p><h2>Studio</h2><p class="price">Affordable monthly</p><p>For working artists who need a serious public portfolio and admin tools.</p><ul><li>Expanded artwork inventory</li><li>Portfolio sections and event history</li><li>Email signup and contact-message admin</li><li>Tenant CSS editor</li><li>Tenant analytics</li></ul><a class="button primary" href="/signup">Choose Studio</a></article>
    <article class="pricing-card"><p class="eyebrow">Professional</p><h2>Custom Domain</h2><p class="price">Higher tier</p><p>For artists who need their own domain and more formal collector-facing presentation.</p><ul><li>Everything in Studio</li><li>Custom domain support</li><li>DNS verification workflow</li><li>Priority setup help</li><li>Advanced branding controls</li></ul><a class="button secondary" href="/contact">Discuss domain setup</a></article>
    <article class="pricing-card"><p class="eyebrow">Teams</p><h2>Collective</h2><p class="price">By scope</p><p>For galleries, artist groups, estates, and multi-person practices.</p><ul><li>Multi-user administration</li><li>Larger portfolio capacity</li><li>Operational support</li><li>Migration planning</li><li>Custom onboarding</li></ul><a class="button secondary" href="/contact">Contact ArtsFolio</a></article>
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

    private function layout(string $title, string $body): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/platform-custom.css">
</head>
<body>
<header class="platform-header"><a class="platform-brand logo-brand" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a href="/pricing">Pricing</a><a href="/directory">Artists</a><a href="/help">Help</a><a href="/login">Sign in</a></nav></header>
<main>{$body}</main>
<footer class="platform-footer"><span>© artsfol.io</span><nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer>
</body>
</html>
HTML;
    }
}

// End of file.
