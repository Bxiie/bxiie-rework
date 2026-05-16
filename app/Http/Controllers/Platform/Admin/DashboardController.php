<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
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

        $email = htmlspecialchars((string) ($currentUser['email'] ?? ''), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Platform Admin | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Platform Admin</h1>
<p>Signed in as {$email}</p>

<ul>
    <li><a href="/admin/tenants">Tenants</a></li>
    <li>Custom domains: coming soon</li>
    <li><a href="/admin/email-outbox">Email outbox</a></li>
    <li><a href="/admin/audit-log">Audit log</a></li>
</ul>
</body>
</html>
HTML);
    }
}

// End of file.
