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
 * Handles platform-owned settings including global CSS and directory behavior.
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
        $persistentLoginDays = $this->escape($this->settings->get('persistent_login_days', '30'));
        $platformCustomCss = $this->escape($this->settings->get('platform_custom_css', ''));
        $directoryEnabled = $this->truthy($this->settings->get('platform_directory_enabled', '1')) ? ' checked' : '';

        return Response::html(AdminLayout::render(
            title: 'Platform Settings',
            active: 'settings',
            body: <<<HTML
<form class="admin-form" method="post" action="/admin/platform-settings">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <div class="admin-form-grid">
        <fieldset><legend>Platform identity</legend><label>Platform name<input type="text" name="platform_name" value="{$platformName}" required></label><label>Support email<input type="email" name="support_email" value="{$supportEmail}"></label></fieldset>
        <fieldset><legend>Authentication</legend><label>Persistent login days<input type="number" name="persistent_login_days" min="1" max="365" value="{$persistentLoginDays}"></label><p class="admin-muted">Browser-session login expires when the browser session ends. “Keep me logged in” uses this many days.</p></fieldset>
        <fieldset><legend>Directory</legend><label><span><input type="checkbox" name="platform_directory_enabled" value="1"{$directoryEnabled}> Enable public artist directory</span></label><p class="admin-muted">Tenant opt-in still applies. This switch controls whether the platform directory is available at all.</p></fieldset>
        <fieldset><legend>Domains</legend><label>Expected IPv4 for custom domain DNS checks<input type="text" name="expected_ipv4" value="{$expectedIpv4}"></label></fieldset>
    </div>
    <fieldset class="admin-panel-wide"><legend>Platform custom CSS</legend><p class="admin-muted">Applied to platform marketing, pricing, help, and platform admin pages through <code>/assets/platform-custom.css</code>.</p><textarea name="platform_custom_css" rows="18" spellcheck="false">{$platformCustomCss}</textarea></fieldset>
    <button type="submit">Save platform settings</button>
</form>
HTML,
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
        $persistentLoginDays = (int) ($_POST['persistent_login_days'] ?? 30);
        $platformCustomCss = (string) ($_POST['platform_custom_css'] ?? '');
        $directoryEnabled = isset($_POST['platform_directory_enabled']) ? '1' : '0';

        if ($platformName === '') {
            return Response::html('<h1>Platform name is required</h1>', 422);
        }
        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid support email</h1>', 422);
        }
        if ($expectedIpv4 !== '' && !filter_var($expectedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return Response::html('<h1>Invalid expected IPv4</h1>', 422);
        }
        if ($persistentLoginDays < 1 || $persistentLoginDays > 365) {
            return Response::html('<h1>Persistent login days must be between 1 and 365</h1>', 422);
        }

        $before = [
            'platform_name' => $this->settings->get('platform_name', 'ArtsFolio'),
            'support_email' => $this->settings->get('support_email', ''),
            'expected_ipv4' => $this->settings->get('expected_ipv4', getenv('ARTSFOLIO_EXPECTED_IPV4') ?: ''),
            'persistent_login_days' => $this->settings->get('persistent_login_days', '30'),
            'platform_directory_enabled' => $this->settings->get('platform_directory_enabled', '1'),
            'platform_custom_css_sha1' => sha1((string) $this->settings->get('platform_custom_css', '')),
        ];

        $this->settings->set('platform_name', $platformName);
        $this->settings->set('support_email', $supportEmail);
        $this->settings->set('expected_ipv4', $expectedIpv4);
        $this->settings->set('persistent_login_days', (string) $persistentLoginDays);
        $this->settings->set('platform_directory_enabled', $directoryEnabled);
        $this->settings->set('platform_custom_css', $platformCustomCss);
        FlashMessages::success('Platform settings saved.');

        $this->auditAction($request, $currentUser, [
            'before' => $before,
            'after' => [
                'platform_name' => $platformName,
                'support_email' => $supportEmail,
                'expected_ipv4' => $expectedIpv4,
                'persistent_login_days' => $persistentLoginDays,
                'platform_directory_enabled' => $directoryEnabled,
                'platform_custom_css_sha1' => sha1($platformCustomCss),
            ],
        ]);

        return new Response('', 302, ['Location' => '/platform/admin/platform-settings']);
    }

    private function auditAction(Request $request, ?array $currentUser, array $details = []): void
    {
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
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }

    private function truthy(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
