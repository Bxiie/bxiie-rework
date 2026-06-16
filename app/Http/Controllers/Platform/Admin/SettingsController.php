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
        $smtpHost = $this->escape($this->settings->get('smtp_host', ''));
        $smtpPort = $this->escape($this->settings->get('smtp_port', '587'));
        $smtpUsername = $this->escape($this->settings->get('smtp_username', ''));
        $smtpPassword = $this->escape($this->settings->get('smtp_password', ''));
        $smtpEncryption = $this->escape($this->settings->get('smtp_encryption', 'tls'));
        $smtpMessageStream = $this->escape($this->settings->get('smtp_x_pm_message_stream', ''));
        $mailFromEmail = $this->escape($this->settings->get('mail_from_email', ''));
        $mailFromName = $this->escape($this->settings->get('mail_from_name', 'ArtsFolio'));
        $stripePublishableKey = $this->escape($this->settings->get('stripe_publishable_key', ''));
        $stripeSecretKey = $this->escape($this->settings->get('stripe_secret_key', ''));
        $stripeWebhookSecret = $this->escape($this->settings->get('stripe_webhook_secret', ''));
        $expectedIpv4 = $this->escape($this->settings->get('expected_ipv4', getenv('ARTSFOLIO_EXPECTED_IPV4') ?: ''));
        $persistentLoginDays = $this->escape($this->settings->get('persistent_login_days', '30'));
        $platformCustomCss = $this->escape($this->settings->get('platform_custom_css', ''));
        $directoryEnabled = $this->truthy($this->settings->get('platform_directory_enabled', '1')) ? ' checked' : '';
        $platformFooterCopyright = $this->escape($this->settings->get('platform_footer_copyright_html', '© {year} ArtsFolio'));
        $directoryThumbSize = $this->escape($this->settings->get('platform_directory_thumbnail_size', '180'));
        $googleClientId = $this->escape($this->settings->get('google_oauth_client_id', ''));
        $googleClientSecret = $this->escape($this->settings->get('google_oauth_client_secret', ''));
        $facebookClientId = $this->escape($this->settings->get('facebook_oauth_client_id', ''));
        $facebookClientSecret = $this->escape($this->settings->get('facebook_oauth_client_secret', ''));
        $oauthAuthBaseUrl = $this->escape($this->settings->get('oauth_auth_base_url', 'https://artsfol.io'));
        $turnstileSiteKey = $this->escape($this->settings->get('turnstile_site_key', $this->settings->get('recaptcha_site_key', '')));
        $turnstileSecretKey = $this->escape($this->settings->get('turnstile_secret_key', ''));
        $signupCodeRequired = $this->truthy($this->settings->get('tenant_signup_code_required', '0')) ? ' checked' : '';

        return Response::html(AdminLayout::render(
            title: 'Platform Settings',
            active: 'settings',
            body: <<<HTML
<form class="admin-form" method="post" action="/platform/admin/platform-settings">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <div class="admin-form-grid">
        <fieldset><legend>Platform identity</legend><label>Platform name<input type="text" name="platform_name" value="{$platformName}" required></label><label>Support email<input type="email" name="support_email" value="{$supportEmail}"></label><label>Footer copyright HTML<input type="text" name="platform_footer_copyright_html" value="{$platformFooterCopyright}"></label><p class="admin-muted">Use {year} for the current year. Safe inline tags only.</p></fieldset>
        <fieldset><legend>Authentication</legend><label>Persistent login days<input type="number" name="persistent_login_days" min="1" max="365" value="{$persistentLoginDays}"></label><label><span><input type="checkbox" name="tenant_signup_code_required" value="1"{$signupCodeRequired}> Require a signup passcode to create new tenant sites</span></label><p class="admin-muted">Use Platform Codes to create one-time or blanket passcodes for prospective tenants.</p></fieldset>
        <fieldset><legend>Directory</legend><label><span><input type="checkbox" name="platform_directory_enabled" value="1"{$directoryEnabled}> Enable public artist directory</span></label><label>Directory thumbnail size, px<input type="number" name="platform_directory_thumbnail_size" min="80" max="420" value="{$directoryThumbSize}"></label><p class="admin-muted">Tenant opt-in still applies. This switch controls whether the platform directory is available at all.</p></fieldset>
        <fieldset><legend>Domains</legend><label>Expected IPv4 for custom domain DNS checks<input type="text" name="expected_ipv4" value="{$expectedIpv4}"></label></fieldset>
    </div>
    <fieldset><legend>OAuth providers</legend><div class="admin-form-grid"><label>OAuth callback base URL<input type="url" name="oauth_auth_base_url" value="{$oauthAuthBaseUrl}" placeholder="https://artsfol.io"></label><label>Google client ID<input type="text" name="google_oauth_client_id" value="{$googleClientId}"></label><label>Google client secret<input type="password" name="google_oauth_client_secret" value="{$googleClientSecret}"></label><label>Facebook client ID<input type="text" name="facebook_oauth_client_id" value="{$facebookClientId}"></label><label>Facebook client secret<input type="password" name="facebook_oauth_client_secret" value="{$facebookClientSecret}"></label></div><p class="admin-muted">Stored in <code>platform_settings</code>. Use the platform origin, normally <code>https://artsfol.io</code>, so provider callback URLs stay stable across tenant domains. Do not expose provider secrets in <code>PROJECT_STATE.md</code> or docs.</p></fieldset>
    <fieldset><legend>Spam protection</legend><div class="admin-form-grid"><label>Cloudflare Turnstile site key<input type="text" name="turnstile_site_key" value="{$turnstileSiteKey}"></label><label>Cloudflare Turnstile secret key<input type="password" name="turnstile_secret_key" value="{$turnstileSecretKey}"></label></div><p class="admin-muted">When the secret key is blank, public contact and signup forms do not block submissions in development. Create keys in Cloudflare Turnstile and allow artsfol.io plus active tenant domains.</p></fieldset>
    <fieldset><legend>Email delivery</legend><div class="admin-form-grid"><label>SMTP host<input type="text" name="smtp_host" value="{$smtpHost}"></label><label>SMTP port<input type="number" name="smtp_port" value="{$smtpPort}" min="1" max="65535"></label><label>SMTP username<input type="text" name="smtp_username" value="{$smtpUsername}"></label><label>SMTP password<input type="password" name="smtp_password" value="{$smtpPassword}"></label><label>SMTP encryption<input type="text" name="smtp_encryption" value="{$smtpEncryption}" placeholder="tls, ssl, or none"></label><label>From email<input type="email" name="mail_from_email" value="{$mailFromEmail}"></label><label>From name<input type="text" name="mail_from_name" value="{$mailFromName}"></label><label>Postmark message stream<input type="text" name="smtp_x_pm_message_stream" value="{$smtpMessageStream}" placeholder="outbound or broadcasts"></label></div><p class="admin-muted">Postmark message stream is sent as <code>X-PM-Message-Stream</code>. These values are stored in <code>platform_settings</code>. Keep production backups and database access restricted because SMTP and ecommerce secrets are sensitive.</p></fieldset>
    <fieldset><legend>Ecommerce</legend><div class="admin-form-grid"><label>Stripe publishable key<input type="text" name="stripe_publishable_key" value="{$stripePublishableKey}"></label><label>Stripe secret key<input type="password" name="stripe_secret_key" value="{$stripeSecretKey}"></label><label>Stripe webhook secret<input type="password" name="stripe_webhook_secret" value="{$stripeWebhookSecret}"></label></div></fieldset>
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
        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = (int) ($_POST['smtp_port'] ?? 587);
        $smtpUsername = trim((string) ($_POST['smtp_username'] ?? ''));
        $smtpPassword = (string) ($_POST['smtp_password'] ?? '');
        $smtpEncryption = trim((string) ($_POST['smtp_encryption'] ?? 'tls'));
        $smtpMessageStream = trim((string) ($_POST['smtp_x_pm_message_stream'] ?? ''));
        $mailFromEmail = trim((string) ($_POST['mail_from_email'] ?? ''));
        $mailFromName = trim((string) ($_POST['mail_from_name'] ?? 'ArtsFolio'));
        $stripePublishableKey = trim((string) ($_POST['stripe_publishable_key'] ?? ''));
        $stripeSecretKey = (string) ($_POST['stripe_secret_key'] ?? '');
        $stripeWebhookSecret = (string) ($_POST['stripe_webhook_secret'] ?? '');
        $directoryEnabled = isset($_POST['platform_directory_enabled']) ? '1' : '0';
        $platformFooterCopyright = trim((string) ($_POST['platform_footer_copyright_html'] ?? '© {year} ArtsFolio'));
        $directoryThumbSize = max(80, min(420, (int) ($_POST['platform_directory_thumbnail_size'] ?? 180)));
        $googleClientId = trim((string) ($_POST['google_oauth_client_id'] ?? ''));
        $googleClientSecret = (string) ($_POST['google_oauth_client_secret'] ?? '');
        $facebookClientId = trim((string) ($_POST['facebook_oauth_client_id'] ?? ''));
        $facebookClientSecret = (string) ($_POST['facebook_oauth_client_secret'] ?? '');
        $oauthAuthBaseUrl = rtrim(trim((string) ($_POST['oauth_auth_base_url'] ?? 'https://artsfol.io')), '/');
        $turnstileSiteKey = trim((string) ($_POST['turnstile_site_key'] ?? ''));
        $turnstileSecretKey = (string) ($_POST['turnstile_secret_key'] ?? '');
        $signupCodeRequired = isset($_POST['tenant_signup_code_required']) ? '1' : '0';

        if ($platformName === '') {
            return Response::html('<h1>Platform name is required</h1>', 422);
        }
        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid support email</h1>', 422);
        }
        if ($expectedIpv4 !== '' && !filter_var($expectedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return Response::html('<h1>Invalid expected IPv4</h1>', 422);
        }
        if ($mailFromEmail !== '' && !filter_var($mailFromEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid from email</h1>', 422);
        }
        if ($smtpPort < 1 || $smtpPort > 65535) {
            return Response::html('<h1>Invalid SMTP port</h1>', 422);
        }
        if ($smtpMessageStream !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $smtpMessageStream)) {
            return Response::html('<h1>Invalid Postmark message stream</h1>', 422);
        }
        if ($persistentLoginDays < 1 || $persistentLoginDays > 365) {
            return Response::html('<h1>Persistent login days must be between 1 and 365</h1>', 422);
        }
        if ($oauthAuthBaseUrl !== '' && !preg_match('#^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?$#', $oauthAuthBaseUrl)) {
            return Response::html('<h1>OAuth callback base URL must be an HTTPS origin without a path</h1>', 422);
        }

        $before = [
            'platform_name' => $this->settings->get('platform_name', 'ArtsFolio'),
            'support_email' => $this->settings->get('support_email', ''),
            'expected_ipv4' => $this->settings->get('expected_ipv4', getenv('ARTSFOLIO_EXPECTED_IPV4') ?: ''),
            'persistent_login_days' => $this->settings->get('persistent_login_days', '30'),
            'platform_directory_enabled' => $this->settings->get('platform_directory_enabled', '1'),
            'platform_custom_css_sha1' => sha1((string) $this->settings->get('platform_custom_css', '')),
            'smtp_host' => $this->settings->get('smtp_host', ''),
            'smtp_x_pm_message_stream' => $this->settings->get('smtp_x_pm_message_stream', ''),
            'mail_from_email' => $this->settings->get('mail_from_email', ''),
            'stripe_publishable_key' => $this->settings->get('stripe_publishable_key', ''),
            'oauth_auth_base_url' => $this->settings->get('oauth_auth_base_url', 'https://artsfol.io'),
            'google_oauth_client_id' => $this->settings->get('google_oauth_client_id', ''),
            'facebook_oauth_client_id' => $this->settings->get('facebook_oauth_client_id', ''),
        ];

        $this->settings->set('platform_name', $platformName);
        $this->settings->set('support_email', $supportEmail);
        $this->settings->set('expected_ipv4', $expectedIpv4);
        $this->settings->set('persistent_login_days', (string) $persistentLoginDays);
        $this->settings->set('platform_directory_enabled', $directoryEnabled);
        $this->settings->set('platform_footer_copyright_html', $platformFooterCopyright);
        $this->settings->set('platform_directory_thumbnail_size', (string) $directoryThumbSize);
        $this->settings->set('oauth_auth_base_url', $oauthAuthBaseUrl !== '' ? $oauthAuthBaseUrl : 'https://artsfol.io');
        $this->settings->set('google_oauth_client_id', $googleClientId);
        $this->settings->set('google_oauth_client_secret', $googleClientSecret);
        $this->settings->set('facebook_oauth_client_id', $facebookClientId);
        $this->settings->set('facebook_oauth_client_secret', $facebookClientSecret);
        $this->settings->set('turnstile_site_key', $turnstileSiteKey);
        $this->settings->set('turnstile_secret_key', $turnstileSecretKey);
        $this->settings->set('tenant_signup_code_required', $signupCodeRequired);
        $this->settings->set('platform_custom_css', $platformCustomCss);
        $this->settings->set('smtp_host', $smtpHost);
        $this->settings->set('smtp_port', (string) $smtpPort);
        $this->settings->set('smtp_username', $smtpUsername);
        $this->settings->set('smtp_password', $smtpPassword);
        $this->settings->set('smtp_encryption', $smtpEncryption);
        $this->settings->set('smtp_x_pm_message_stream', $smtpMessageStream);
        $this->settings->set('mail_from_email', $mailFromEmail);
        $this->settings->set('mail_from_name', $mailFromName);
        $this->settings->set('stripe_publishable_key', $stripePublishableKey);
        $this->settings->set('stripe_secret_key', $stripeSecretKey);
        $this->settings->set('stripe_webhook_secret', $stripeWebhookSecret);
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
                'smtp_host' => $smtpHost,
                'smtp_x_pm_message_stream' => $smtpMessageStream,
                'mail_from_email' => $mailFromEmail,
                'stripe_publishable_key' => $stripePublishableKey,
                'oauth_auth_base_url' => $oauthAuthBaseUrl !== '' ? $oauthAuthBaseUrl : 'https://artsfol.io',
                'google_oauth_client_id' => $googleClientId,
                'facebook_oauth_client_id' => $facebookClientId,
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
