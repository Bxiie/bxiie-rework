<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;

/**
 * Platform admin dashboard with explanatory action cards.
 */
final class DashboardController
{
    public function __construct(private readonly RequirePlatformRole $roles)
    {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $email = AdminLayout::escape((string) ($currentUser['email'] ?? ''));
        $body = <<<HTML
<p class="admin-muted">Signed in as {$email}. Platform admin controls ArtsFolio-wide operations, settings, routing, tenants, domains, and support tooling.</p>
<div class="admin-card-grid">
    <a class="admin-card" href="/platform/admin/tenants"><h2>Tenants</h2><p>Review tenant sites, account status, and platform-level tenant inventory.</p></a>
    <a class="admin-card" href="/platform/admin/platform-settings"><h2>Platform Settings</h2><p>Manage ArtsFolio branding, support email, platform CSS, auth duration, and directory availability.</p></a>
    <a class="admin-card" href="/platform/admin/stats"><h2>Platform Stats</h2><p>Review platform-level traffic and operating signals.</p></a>
    <a class="admin-card" href="/platform/admin/audit-log"><h2>Audit Log</h2><p>Inspect authentication and administrative changes.</p></a>
    <a class="admin-card" href="/platform/admin/domains"><h2>Domains</h2><p>Verify custom domains and inspect rendered vhost artifacts.</p></a>
    <a class="admin-card" href="/platform/admin/jobs"><h2>Jobs</h2><p>Review queued background jobs and retry failed work.</p></a>
    <a class="admin-card" href="/platform/admin/email-outbox"><h2>Email Outbox</h2><p>Inspect queued and sent platform email.</p></a>
    <a class="admin-card" href="/help/developer"><h2>Developer Reference</h2><p>Read implementation-focused endpoint and route documentation.</p></a>
</div>
HTML;

        return Response::html(AdminLayout::render(title: 'Platform Admin', body: $body, active: 'dashboard'));
    }
}

// End of file.
