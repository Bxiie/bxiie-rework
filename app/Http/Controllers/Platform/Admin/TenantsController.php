<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Membership\Roles;
use App\Platform\Tenants\TenantAdminRepository;

/**
 * Handles platform-admin tenant list screen.
 */
final class TenantsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly TenantAdminRepository $tenants,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $rows = '';

        foreach ($this->tenants->latest() as $tenant) {
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $tenant['id']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['slug']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['name']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['status']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['domain_count']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">No tenants found.</td></tr>';
        }

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tenants | Platform Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Tenants</h1>

<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Slug</th>
            <th>Name</th>
            <th>Status</th>
            <th>Domains</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>

<p><a href="/admin">Back to platform admin</a></p>
</body>
</html>
HTML);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
