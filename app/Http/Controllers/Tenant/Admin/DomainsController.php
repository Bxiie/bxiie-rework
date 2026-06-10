<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Domains\DomainAdminService;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantDomainRepository;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use PDO;

/**
 * Tenant-admin custom domain management.
 */
final class DomainsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly CsrfTokenService $csrf,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $repo = new TenantDomainRepository($this->pdo);
        $domains = $repo->listForTenant($tenant->tenantId);
        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $rows = '';

        foreach ($domains as $domain) {
            $id = (int) $domain['id'];
            $hostname = AdminLayout::escape((string) $domain['hostname']);
            $type = AdminLayout::escape((string) $domain['domain_type']);
            $status = AdminLayout::escape((string) $domain['status']);
            $delete = ((string) $domain['domain_type'] === 'subdomain' || str_ends_with((string) $domain['hostname'], '.artsfol.io'))
                ? '<span class="admin-muted">Default domain</span>'
                : '<form method="post" action="/admin/domains/action" class="admin-inline-form" onsubmit="return confirm(&quot;Delete this custom domain?&quot;);"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="domain_id" value="' . $id . '"><input type="hidden" name="custom_domain_action" value="delete"><button type="submit">Delete</button></form>';
            $verify = ((string) $domain['domain_type'] === 'subdomain')
                ? ''
                : '<form method="post" action="/admin/domains/action" class="admin-inline-form"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="domain_id" value="' . $id . '"><input type="hidden" name="custom_domain_action" value="verify_dns"><button type="submit">Verify DNS</button></form>';
            $rows .= "<tr><td>{$hostname}</td><td>{$type}</td><td>{$status}</td><td>{$verify}{$delete}</td></tr>";
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4">No domains configured.</td></tr>';
        }

        $body = <<<HTML
<section class="admin-card">
  <h2>Domains</h2>
  <p>Add custom domains for this tenant. Your default artsfol.io subdomain does not count against plan limits. The www hostname for an existing apex domain shares the same plan allowance.</p>
  <form method="post" action="/admin/domains/action" class="admin-form">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="custom_domain_action" value="add">
    <label>Custom domain<input type="text" name="hostname" placeholder="example.com" required></label>
    <button type="submit">Add custom domain</button>
  </form>
</section>
<table class="admin-table">
  <thead><tr><th>Hostname</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
HTML;

        return Response::html(AdminLayout::render('Domains', $body, 'domains'));
    }

    public function action(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $action = (string) ($_POST['custom_domain_action'] ?? '');
        $service = new DomainAdminService($this->pdo);
        $repo = new TenantDomainRepository($this->pdo);

        try {
            if ($action === 'add') {
                $service->addCustomDomain($tenant->tenantId, (string) ($_POST['hostname'] ?? ''));
                FlashMessages::success('Custom domain added. Verify DNS after pointing the hostname at ArtsFolio.');
            } elseif ($action === 'delete') {
                $repo->deleteDomain($tenant->tenantId, (int) ($_POST['domain_id'] ?? 0));
                FlashMessages::success('Custom domain deleted.');
            } elseif ($action === 'verify_dns') {
                $domainId = (int) ($_POST['domain_id'] ?? 0);
                $service->queueDnsVerification($domainId);
                FlashMessages::success('DNS verification queued.');
            } else {
                return Response::html('<h1>Invalid domain action</h1>', 422);
            }
        } catch (\Throwable $e) {
            FlashMessages::error('Domain action failed: ' . $e->getMessage());
        }

        return new Response('', 303, ['Location' => '/admin/domains']);
    }
}

// End of file.
