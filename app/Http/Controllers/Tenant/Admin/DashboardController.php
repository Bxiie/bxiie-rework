<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Http\View\TenantAdminLayout;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Platform\Tenancy\TenantContext;

final class DashboardController
{
    public function __construct(
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        $body = <<<HTML
<p class="admin-muted">Manage the public site, artwork catalog, engagement, and reporting.</p>

<div class="dashboard-grid">
    <a class="dashboard-card" href="/admin/settings"><h3>Site Settings</h3><p>Branding, tabs, slugs, CSS, homepage text, backgrounds, and SEO.</p></a>
    <a class="dashboard-card" href="/admin/content"><h3>Content</h3><p>About text, contact text, page images, and public content blocks.</p></a>
    <a class="dashboard-card" href="/admin/artworks"><h3>Artworks</h3><p>Upload, edit, publish, archive, sort, filter, and manage artwork metadata.</p></a>
    <a class="dashboard-card" href="/admin/portfolio-sections"><h3>Portfolio Sections</h3><p>Organize artworks and control public portfolio tabs.</p></a>
    <a class="dashboard-card" href="/admin/events"><h3>Events / Exhibitions</h3><p>Edit exhibition history and public presentation.</p></a>
    <a class="dashboard-card" href="/admin/contact-messages"><h3>Contact Messages</h3><p>Review and delete public contact form messages.</p></a>
    <a class="dashboard-card" href="/admin/email-signups"><h3>Email Signups</h3><p>View, import, export, and manage subscribers.</p></a>
    <a class="dashboard-card" href="/admin/stats"><h3>Stats</h3><p>Traffic, artwork views, location rollups, and engagement analytics.</p></a>
    <a class="dashboard-card" href="/admin/audit-log"><h3>Audit Log</h3><p>Review tenant admin activity and security-relevant changes.</p></a>
</div>
HTML;

        return Response::html((new TenantAdminLayout($this->settings))->render($tenant, 'Tenant Admin', $body, 'dashboard'));
    }
}

// End of file.
