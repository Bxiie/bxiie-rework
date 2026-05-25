<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;

/**
 * Handles platform-owned settings.
 */
final class SettingsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly PlatformSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function edit(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageSettings($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $csrf = $this->escape($this->csrf->getOrCreate());
        $platformName = $this->escape($this->settings->get('platform_name', 'ArtsFolio'));
        $supportEmail = $this->escape($this->settings->get('support_email', ''));
        $expectedIpv4 = $this->escape($this->settings->get('expected_ipv4', getenv('ARTSFOLIO_EXPECTED_IPV4') ?: ''));

        return Response::html(AdminLayout::render(
            title: 'Platform Settings | ArtsFolio',
            body: <<<HTML
<form class="admin-form" method="post" action="/admin/platform-settings">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p>
        <label>Platform name<br>
            <input type="text" name="platform_name" value="{$platformName}" required>
        </label>
    </p>
    <p>
        <label>Support email<br>
            <input type="email" name="support_email" value="{$supportEmail}">
        </label>
    </p>
    <p>
        <label>Expected IPv4 for custom domain DNS checks<br>
            <input type="text" name="expected_ipv4" value="{$expectedIpv4}">
        </label>
    </p>
    <button type="submit">Save platform settings</button>
</form>
HTML,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/tenants' => 'Tenants',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
            ],
        ));
    }

    public function update(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageSettings($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $platformName = trim((string) ($_POST['platform_name'] ?? ''));
        $supportEmail = trim((string) ($_POST['support_email'] ?? ''));
        $expectedIpv4 = trim((string) ($_POST['expected_ipv4'] ?? ''));

        if ($platformName === '') {
            return Response::html('<h1>Platform name is required</h1>', 422);
        }

        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid support email</h1>', 422);
        }

        if ($expectedIpv4 !== '' && !filter_var($expectedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return Response::html('<h1>Invalid expected IPv4</h1>', 422);
        }

        $before = [
            'platform_name' => $this->settings->get('platform_name', 'ArtsFolio'),
            'support_email' => $this->settings->get('support_email', ''),
            'expected_ipv4' => $this->settings->get('expected_ipv4', getenv('ARTSFOLIO_EXPECTED_IPV4') ?: ''),
        ];

        $this->settings->set('platform_name', $platformName);
        $this->settings->set('support_email', $supportEmail);
        $this->settings->set('expected_ipv4', $expectedIpv4);
        FlashMessages::success('Platform settings saved.');

        $this->auditAction(
            request: $request,
            currentUser: $currentUser,
            details: [
                'before' => $before,
                'after' => [
                    'platform_name' => $platformName,
                    'support_email' => $supportEmail,
                    'expected_ipv4' => $expectedIpv4,
                ],
            ],
        );

        return Response::html(AdminLayout::render(
            title: 'Platform settings saved',
            body: '<p>Platform settings have been updated.</p><p><a class="admin-button" href="/admin/platform-settings">Back to platform settings</a></p>',
            nav: [
                '/admin' => 'Dashboard',
                '/admin/tenants' => 'Tenants',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
            ],
        ));
    }

    private function auditAction(
        Request $request,
        ?array $currentUser,
        array $details = [],
    ): void {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record(
            action: 'platform.settings.updated',
            userId: isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            entityType: 'platform_settings',
            entityId: 'global',
            details: $details,
            ipAddress: $request->server('REMOTE_ADDR'),
        );
    }

    private function canManageSettings(?array $currentUser): bool
    {
        return $this->roles->allows(
            currentUser: $currentUser,
            allowedRoles: [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN],
        );
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
