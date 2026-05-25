<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Http\View\TenantAdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Lets tenant admins opt the tenant into the public ArtsFolio directory.
 */
final class DiscoverySettingsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $layout = new TenantAdminLayout($this->settings);
        $token = $this->escape($this->csrf->getOrCreate());
        $checked = $this->truthy($this->settings->get($tenant, 'platform_directory_opt_in', '0') ?? '0') ? ' checked' : '';
        $summary = $this->escape($this->settings->get($tenant, 'platform_directory_summary', '') ?? '');
        $notice = isset($_GET['notice']) ? '<p class="notice">Directory settings saved.</p>' : '';

        $body = <<<HTML
{$notice}
<p class="admin-muted">Control whether this artist appears in the public ArtsFolio directory on artsfol.io. The platform-wide directory switch must also be enabled by a platform admin.</p>

<form method="post" action="/admin/directory" class="admin-form">
    <input type="hidden" name="csrf_token" value="{$token}">

    <fieldset>
        <legend>Directory listing</legend>
        <label class="checkbox-row">
            <span><input type="checkbox" name="platform_directory_opt_in" value="1"{$checked}> Show this tenant in the public ArtsFolio directory</span>
        </label>
        <p class="admin-muted">Leave this off for private portfolios, sites still in setup, or artists who do not want platform-level discovery.</p>
    </fieldset>

    <fieldset>
        <legend>Directory summary</legend>
        <label>Short public description
            <textarea name="platform_directory_summary" rows="5" maxlength="500">{$summary}</textarea>
        </label>
        <p class="admin-muted">This appears on directory cards. Keep it plain, specific, and collector-readable.</p>
    </fieldset>

    <p><button type="submit">Save directory settings</button></p>
</form>
HTML;

        return Response::html($layout->render($tenant, 'Directory', $body, 'directory'));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $optIn = isset($_POST['platform_directory_opt_in']) ? '1' : '0';
        $summary = trim((string) ($_POST['platform_directory_summary'] ?? ''));
        $this->settings->set($tenant, 'platform_directory_opt_in', $optIn);
        $this->settings->set($tenant, 'platform_directory_summary', $summary);

        if ($this->auditLog) {
            $this->auditLog->record([
                'tenant_id' => $tenant->tenantId,
                'actor_user_id' => $currentUser['user_id'] ?? null,
                'action' => 'tenant.directory_settings.updated',
                'entity_type' => 'tenant',
                'entity_id' => $tenant->tenantId,
                'metadata' => ['platform_directory_opt_in' => $optIn],
            ]);
        }

        return new Response('', 303, ['Location' => '/admin/directory?notice=saved']);
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
