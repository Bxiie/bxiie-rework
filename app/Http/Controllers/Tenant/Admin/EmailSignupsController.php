<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;
use App\Support\Csv\CsvResponse;
use App\Tenant\Signup\EmailSignupRepository;

/**
 * Handles tenant-admin email signup list and export screens.
 */
final class EmailSignupsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly EmailSignupRepository $signups,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $rows = '';

        foreach ($this->signups->latestForTenant($tenant, 50) as $signup) {
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $signup['id']) . '</td>'
                . '<td>' . $this->escape((string) $signup['email']) . '</td>'
                . '<td>' . $this->escape((string) ($signup['name'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($signup['source'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $signup['consent_status']) . '</td>'
                . '<td>' . $this->escape((string) $signup['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">No email signups found.</td></tr>';
        }

        $tenantName = $this->escape($tenant->name);

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Email Signups | {$tenantName}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Email Signups</h1>
<p><a href="/admin/email-signups.csv">Export CSV</a></p>

<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Name</th>
            <th>Source</th>
            <th>Consent</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>

<p><a href="/admin">Back to tenant admin</a></p>
</body>
</html>
HTML);
    }

    public function export(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $rows = [];

        foreach ($this->signups->latestForTenant($tenant, 1000) as $signup) {
            $rows[] = [
                'id' => (string) $signup['id'],
                'email' => (string) $signup['email'],
                'name' => (string) ($signup['name'] ?? ''),
                'source' => (string) ($signup['source'] ?? ''),
                'consent_status' => (string) $signup['consent_status'],
                'confirmed_at' => (string) ($signup['confirmed_at'] ?? ''),
                'unsubscribed_at' => (string) ($signup['unsubscribed_at'] ?? ''),
                'created_at' => (string) $signup['created_at'],
            ];
        }

        return CsvResponse::download(
            filename: 'email-signups-' . $tenant->slug . '.csv',
            headers: ['id', 'email', 'name', 'source', 'consent_status', 'confirmed_at', 'unsubscribed_at', 'created_at'],
            rows: $rows,
        );
    }

    private function canView(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows(
            currentUser: $currentUser,
            tenant: $tenant,
            allowedRoles: [Roles::TENANT_OWNER, Roles::TENANT_ADMIN, Roles::TENANT_EDITOR],
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
