<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;

final class ContributorDashboardController
{
    public function __construct(private readonly MembershipRepository $memberships) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$currentUser || empty($currentUser['user_id'])) {
            return new Response('', 303, ['Location' => '/login']);
        }
        $roles = $this->memberships->tenantRolesForUser($tenant->tenantId, (int) $currentUser['user_id']);
        if (!array_intersect($roles, ['tenant_owner', 'tenant_admin', 'owner', 'admin', 'editor', 'user'])) {
            return Response::html('<h1>Access denied</h1><p>Tenant contributor access required.</p>', 403);
        }
        $site = htmlspecialchars($tenant->name, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contributor workbench</title><link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/tenant.css"><style>.contributor{max-width:900px;margin:2rem auto;padding:1rem}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}.card{display:block;border:1px solid currentColor;padding:1rem;text-decoration:none}</style></head><body><main class="contributor"><p>ArtsFolio contributor access</p><h1>' . $site . '</h1><p><strong>Your work stays in draft.</strong> An administrator reviews uploads and portfolio sections before publication.</p><div class="grid"><a class="card" href="/admin/artwork/upload"><h2>Upload draft artwork</h2><p>Add an image and basic details.</p></a><a class="card" href="/admin/portfolio-sections/edit"><h2>Create draft section</h2><p>Propose a portfolio section.</p></a><a class="card" href="/portfolio"><h2>Suggest via curation</h2><p>Use the public portfolio workflow to suggest changes.</p></a><a class="card" href="/messages"><h2>Messages</h2><p>Read workflow replies.</p></a></div><p><a href="/">View site</a> · <a href="/logout">Sign out</a></p></main></body></html>';
        return Response::html($html);
    }
}

// End of file.
