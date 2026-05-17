<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Membership\Roles;

/**
 * Handles platform admin dashboard placeholder routes.
 */
final class DashboardController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $email = AdminLayout::escape((string) ($currentUser['email'] ?? ''));

        $body = <<<HTML
<p class="admin-muted">Signed in as {$email}</p>

<ul>
    <li><a href="/admin/tenants">Tenants</a></li>
    <li>Custom domains: coming soon</li>
    <li><a href="/admin/email-outbox">Email outbox</a></li>
    <li><a href="/admin/audit-log">Audit log</a></li>
    <li><a href="/admin/platform-settings">Platform settings</a></li>
</ul>
HTML;

        return Response::html(AdminLayout::render(
            title: 'Platform Admin',
            body: $body,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/tenants' => 'Tenants',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
            ],
        ));
    }
}

// End of file.
