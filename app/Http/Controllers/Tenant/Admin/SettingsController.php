<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Handles tenant-admin editable client settings.
 */
final class SettingsController
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
        if (!$this->canManageSettings($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $csrf = $this->escape($this->csrf->getOrCreate());
        $siteTitle = $this->escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $siteAdminEmail = $this->escape($this->settings->get($tenant, 'site_admin_email', ''));

        return Response::html(AdminLayout::render(
            title: 'Tenant Settings',
            body: <<<HTML
<form class="admin-form" method="post" action="/admin/settings">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p>
        <label>Site title<br>
            <input type="text" name="site_title" value="{$siteTitle}" required>
        </label>
    </p>
    <p>
        <label>Site admin email<br>
            <input type="email" name="site_admin_email" value="{$siteAdminEmail}">
        </label>
    </p>
    <button type="submit">Save settings</button>
</form>
HTML,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/settings' => 'Settings',
                '/admin/contact-messages' => 'Contact Messages',
                '/admin/email-signups' => 'Email Signups',
                '/admin/audit-log' => 'Audit Log',
            ],
        ));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManageSettings($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $siteTitle = trim((string) ($_POST['site_title'] ?? ''));
        $siteAdminEmail = trim((string) ($_POST['site_admin_email'] ?? ''));

        if ($siteTitle === '') {
            return Response::html('<h1>Site title is required</h1>', 422);
        }

        if ($siteAdminEmail !== '' && !filter_var($siteAdminEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid site admin email</h1>', 422);
        }

        $before = [
            'site_title' => $this->settings->get($tenant, 'site_title', $tenant->name),
            'site_admin_email' => $this->settings->get($tenant, 'site_admin_email', ''),
        ];

        $this->settings->set($tenant, 'site_title', $siteTitle);
        $this->settings->set($tenant, 'site_admin_email', $siteAdminEmail);
        FlashMessages::success('Tenant settings saved.');

        $this->auditAction(
            request: $request,
            tenant: $tenant,
            currentUser: $currentUser,
            details: [
                'before' => $before,
                'after' => [
                    'site_title' => $siteTitle,
                    'site_admin_email' => $siteAdminEmail,
                ],
            ],
        );

        return Response::html(AdminLayout::render(
            title: 'Settings saved',
            body: '<p>Tenant settings have been updated.</p><p><a class="admin-button" href="/admin/settings">Back to settings</a></p>',
            nav: [
                '/admin' => 'Dashboard',
                '/admin/settings' => 'Settings',
                '/admin/contact-messages' => 'Contact Messages',
                '/admin/email-signups' => 'Email Signups',
                '/admin/audit-log' => 'Audit Log',
            ],
        ));
    }

    private function auditAction(
        Request $request,
        TenantContext $tenant,
        ?array $currentUser,
        array $details = [],
    ): void {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record(
            action: 'tenant.settings.updated',
            tenantId: $tenant->tenantId,
            userId: isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            entityType: 'tenant_settings',
            entityId: (string) $tenant->tenantId,
            details: $details,
            ipAddress: $request->server('REMOTE_ADDR'),
        );
    }

    private function canManageSettings(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows(
            currentUser: $currentUser,
            tenant: $tenant,
            allowedRoles: [Roles::TENANT_OWNER, Roles::TENANT_ADMIN],
        );
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
