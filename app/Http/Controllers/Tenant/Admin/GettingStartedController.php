<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Platform\Tenancy\TenantContext;

/**
 * Renders the tenant-local setup tour for new tenant administrators.
 */
final class GettingStartedController
{
    public function __construct(private readonly RequireTenantRoleBrowser $roles) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $tenantName = htmlspecialchars($tenant->name, ENT_QUOTES, 'UTF-8');
        $tenantSlug = htmlspecialchars($tenant->slug, ENT_QUOTES, 'UTF-8');
        $publicUrl = htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? $tenant->slug), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Getting started | {$tenantName}</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0;background:#f7f4ee;color:#1e1c18;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.5}main{max-width:1080px;margin:0 auto;padding:32px 20px 52px}.hero,.card{background:#fff;border:1px solid #ded6c8;border-radius:18px;box-shadow:0 8px 24px rgb(0 0 0 / .05)}.hero{padding:28px;margin-bottom:20px}.platform-brand{display:flex;align-items:center;gap:12px;margin-bottom:18px;color:#5f574b;font-size:.92rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em}.platform-brand img{display:block;width:150px;max-width:42vw;height:auto;object-fit:contain}.grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(250px,1fr))}.card{padding:20px}.step{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:999px;background:#1f5f5b;color:#fff;font-weight:800}a.button{display:inline-block;margin-top:10px;padding:9px 14px;border:1px solid #1f5f5b;border-radius:999px;color:#1f5f5b;text-decoration:none}code{background:#f2eee6;border-radius:6px;padding:2px 5px}.resource-list{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}</style></head><body><main><section class="hero"><div class="platform-brand" aria-label="ArtsFolio platform branding"><img src="/assets/logo_2.png" alt="ArtsFolio"><span>Tenant setup powered by ArtsFolio</span></div><p><a href="/admin">&larr; Admin dashboard</a></p><h1>Set up {$tenantName}</h1><p>Your tenant workspace is live. Follow this tour to build a complete site without crawling through every cabinet in the machine room.</p><p><strong>Tenant slug:</strong> <code>{$tenantSlug}</code></p><p><strong>Public URL:</strong> <code>{$publicUrl}</code></p><div class="resource-list"><a class="button" href="/help">Help index</a><a class="button" href="/help/tenant-admin-functions">Function index</a><a class="button" href="/help/training-videos">Training videos</a></div></section><section class="grid" aria-label="Getting started checklist"><article class="card"><p class="step">1</p><h2>Set site identity</h2><p>Confirm site name, labels, colors, typography, logo behavior, contact details, and visibility.</p><a class="button" href="/admin/settings">Edit settings</a></article><article class="card"><p class="step">2</p><h2>Write About and Contact</h2><p>Add concise About copy, contact instructions, and selected site images.</p><a class="button" href="/admin/content">Edit content</a></article><article class="card"><p class="step">3</p><h2>Create portfolio sections</h2><p>Create public groups before uploading a large batch of work.</p><a class="button" href="/admin/portfolio-sections">Manage sections</a></article><article class="card"><p class="step">4</p><h2>Upload first artwork</h2><p>Add one strong record with image, metadata, publication status, and section placement.</p><a class="button" href="/admin/artwork/upload">Upload artwork</a></article><article class="card"><p class="step">5</p><h2>Curate the site</h2><p>Feature work and review public ordering.</p><a class="button" href="/admin/curation">Open curation</a></article><article class="card"><p class="step">6</p><h2>Add events</h2><p>Add exhibitions, fairs, residencies, talks, and public history.</p><a class="button" href="/admin/events">Manage events</a></article><article class="card"><p class="step">7</p><h2>Test engagement</h2><p>Submit contact and signup tests, then review Messages and Email Signups.</p><a class="button" href="/contact">Test contact</a></article><article class="card"><p class="step">8</p><h2>Verify launch</h2><p>Review stats, audit log, tenant routes, public pages, phone layout, and sales readiness.</p><a class="button" href="/admin/routes">Check routes</a></article></section></main></body></html>
HTML);
    }
}

// End of file.
