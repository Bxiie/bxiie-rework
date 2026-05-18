<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Platform\Tenancy\TenantContext;

/**
 * First-run onboarding checklist for newly created tenant admins.
 */
final class GettingStartedController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($tenant, $currentUser, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $tenantName = htmlspecialchars($tenant->name, ENT_QUOTES, 'UTF-8');
        $tenantSlug = htmlspecialchars($tenant->slug, ENT_QUOTES, 'UTF-8');
        $publicUrl = htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? $tenant->slug), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Getting started | {$tenantName}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            background: #f7f4ee;
            color: #1e1c18;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        main {
            max-width: 980px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .hero,
        .card {
            background: #fff;
            border: 1px solid #ded6c8;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgb(0 0 0 / 0.05);
        }

        .hero {
            padding: 28px;
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        }

        .card {
            padding: 20px;
        }

        .step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 999px;
            background: #1f5f5b;
            color: #fff;
            font-weight: 700;
        }

        a.button {
            display: inline-block;
            margin-top: 10px;
            padding: 9px 14px;
            border: 1px solid #1f5f5b;
            border-radius: 999px;
            color: #1f5f5b;
            text-decoration: none;
        }

        code {
            background: #f2eee6;
            border-radius: 6px;
            padding: 2px 5px;
        }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <p><a href="/admin">&larr; Admin dashboard</a></p>
        <h1>Set up {$tenantName}</h1>
        <p>Your tenant workspace is live. The goal is simple: publish a credible portfolio in under ten minutes, not wander into the boiler room with a flashlight.</p>
        <p><strong>Tenant slug:</strong> <code>{$tenantSlug}</code></p>
        <p><strong>Public URL:</strong> <code>{$publicUrl}</code></p>
    </section>

    <section class="grid" aria-label="Getting started checklist">
        <article class="card">
            <p class="step">1</p>
            <h2>Create portfolio sections</h2>
            <p>Start with a small structure: Featured, Sculpture, Digital, Exhibitions, or whatever fits the artist.</p>
            <a class="button" href="/admin/portfolio-sections">Manage sections</a>
        </article>

        <article class="card">
            <p class="step">2</p>
            <h2>Upload first artwork</h2>
            <p>Add one strong artwork record with title, year, medium, dimensions, and a clean image.</p>
            <a class="button" href="/admin/images">Upload artwork</a>
        </article>

        <article class="card">
            <p class="step">3</p>
            <h2>Choose site identity</h2>
            <p>Set the site title, browser title, contact details, and basic theme settings.</p>
            <a class="button" href="/admin/settings">Edit settings</a>
        </article>

        <article class="card">
            <p class="step">4</p>
            <h2>Test public engagement</h2>
            <p>Submit a contact message and email signup so the owner can trust the plumbing.</p>
            <a class="button" href="/contact">Test contact</a>
        </article>
    </section>
</main>
</body>
</html>
HTML);
    }
}

// End of file.
