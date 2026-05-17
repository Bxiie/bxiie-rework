<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
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
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $email = htmlspecialchars((string) ($currentUser['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tenantName = htmlspecialchars($tenant->name, ENT_QUOTES, 'UTF-8');
        $tenantSlug = htmlspecialchars($tenant->slug, ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tenant Admin | {$tenantName}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Tenant Admin</h1>
<p>Tenant: {$tenantName} ({$tenantSlug})</p>
<p>Signed in as {$email}</p>

<ul>
    <li>Client settings: coming soon</li>
    <li>Artwork: coming soon</li>
    <li>Portfolio sections: coming soon</li>
    <li><a href="/admin/contact-messages">Contact messages</a></li>
    <li><a href="/admin/email-signups">Email signups</a></li>
    <li>Account and billing details: coming later</li>
</ul>
</body>
</html>
HTML);
    }
}

// End of file.
