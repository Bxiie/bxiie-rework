<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;

/**
 * Handles tenant admin dashboard placeholder routes.
 */
final class DashboardController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, [Roles::TENANT_OWNER, Roles::TENANT_ADMIN, Roles::TENANT_EDITOR])) {
            return Response::html('<h1>Forbidden</h1>
<section class="admin-card-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin:1.5rem 0;">
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Site settings</h2>
        <p>Titles, tab names, slugs, homepage text, colors, background, tenant CSS, and exhibition display settings.</p>
        <p><a href="/admin/settings">Open site settings</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Content</h2>
        <p>About text, contact details, social links, and page content.</p>
        <p><a href="/admin/content">Edit content</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Artworks</h2>
        <p>Review, filter, edit, publish, unpublish, archive, and upload artwork.</p>
        <p><a href="/admin/artworks">Manage artworks</a> · <a href="/admin/artwork/upload">Upload artwork</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Portfolio sections</h2>
        <p>Create sections, show sections as tabs, and control tab ordering.</p>
        <p><a href="/admin/portfolio-sections">Manage portfolio sections</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Events / exhibitions</h2>
        <p>Add and edit exhibitions shown on the About page.</p>
        <p><a href="/admin/events">Manage events</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Contact messages</h2>
        <p>Review and delete contact form submissions.</p>
        <p><a href="/admin/contact-messages">View messages</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Email signups</h2>
        <p>Review subscribers and export/import the mailing list.</p>
        <p><a href="/admin/email-signups">Manage email list</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Stats</h2>
        <p>Traffic, image views, location rollups, day/hour graphs, and engagement signals.</p>
        <p><a href="/admin/stats">View stats</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Audit log</h2>
        <p>Review administrative changes and security-relevant tenant actions.</p>
        <p><a href="/admin/audit-log">Open audit log</a></p>
    </article>
    <article style="border:1px solid #ddd;border-radius:16px;padding:1rem;background:#fff;">
        <h2>Public site</h2>
        <p>Preview the public tenant site.</p>
        <p><a href="/" target="_blank" rel="noopener">Open public site</a></p>
    </article>
</section><p>Tenant admin access required.</p>', 403);
        }

        $email = AdminLayout::escape((string) ($currentUser['email'] ?? ''));
        $tenantName = AdminLayout::escape($tenant->name);
        $tenantSlug = AdminLayout::escape($tenant->slug);

        $body = <<<HTML
<p class="admin-muted">Tenant: {$tenantName} ({$tenantSlug})</p>
<p class="admin-muted">Signed in as {$email}</p>

<ul>
    <li><a href="/admin/settings">Client settings</a></li>
    <li>Artwork: coming soon</li>
    <li>Portfolio sections: coming soon</li>
    <li><a href="/admin/contact-messages">Contact messages</a></li>
    <li><a href="/admin/email-signups">Email signups</a></li>
    <li><a href="/admin/audit-log">Audit log</a></li>
    <li>Account and billing details: coming later</li>
    <li><a href="/admin/artworks">Artworks</a></li>
</ul>
HTML;

        return Response::html(AdminLayout::render(
            title: 'Tenant Admin',
            body: $body,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/settings' => 'Settings',
                '/admin/contact-messages' => 'Contact Messages',
                '/admin/email-signups' => 'Email Signups',
                '/admin/audit-log' => 'Audit Log',
                '/admin/routes' => 'Routes',
            ],
        ));
    }
}

// End of file.
