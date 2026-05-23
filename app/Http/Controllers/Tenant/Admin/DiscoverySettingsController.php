<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Allows tenant admins to opt into public ArtsFolio discovery.
 */
final class DiscoverySettingsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $token = $this->escape($this->csrf->getOrCreate());
        $checked = $this->truthy($this->settings->get($tenant, 'platform_directory_opt_in', '0')) ? ' checked' : '';
        $summary = $this->escape($this->settings->get($tenant, 'platform_directory_summary', ''));
        $notice = isset($_GET['notice']) ? '<p class="notice">Discovery settings saved.</p>' : '';

        $body = <<<HTML
{$notice}
<p class="admin-muted">Choose whether this tenant appears on the public artsfol.io artist directory and random artwork mosaic.</p>

<form method="post" action="/admin/platform-discovery" class="admin-form">
    <input type="hidden" name="csrf_token" value="{$token}">

    <fieldset>
        <legend>Public discovery</legend>
        <label>
            <span><input type="checkbox" name="platform_directory_opt_in" value="1"{$checked}> Show this tenant in the public ArtsFolio directory</span>
        </label>
        <p class="admin-muted">Only public artwork from opted-in tenants may be shown on artsfol.io.</p>
    </fieldset>

    <fieldset>
        <legend>Directory summary</legend>
        <label>Short description
            <textarea name="platform_directory_summary" rows="5" maxlength="500">{$summary}</textarea>
        </label>
    </fieldset>

    <p><button type="submit">Save discovery settings</button></p>
</form>
HTML;

        return Response::html(AdminLayout::render('Discovery', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $this->settings->set($tenant, 'platform_directory_opt_in', isset($_POST['platform_directory_opt_in']) ? '1' : '0');
        $this->settings->set($tenant, 'platform_directory_summary', trim((string) ($_POST['platform_directory_summary'] ?? '')));

        return new Response('', 303, ['Location' => '/admin/platform-discovery?notice=saved']);
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
