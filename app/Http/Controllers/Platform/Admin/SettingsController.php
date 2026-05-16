<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Membership\Roles;
use App\Platform\Settings\PlatformSettingsRepository;
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
    ) {
    }

    public function edit(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageSettings($currentUser)) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $csrf = $this->escape($this->csrf->getOrCreate());
        $platformName = $this->escape($this->settings->get('platform_name', 'ArtsFolio'));
        $supportEmail = $this->escape($this->settings->get('support_email', ''));
        $expectedIpv4 = $this->escape($this->settings->get('expected_ipv4', getenv('ARTSFOLIO_EXPECTED_IPV4') ?: ''));

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Platform Settings | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Platform Settings</h1>

<form method="post" action="/admin/platform-settings">
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

<p><a href="/admin">Back to platform admin</a></p>
</body>
</html>
HTML);
    }

    public function update(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageSettings($currentUser)) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
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

        $this->settings->set('platform_name', $platformName);
        $this->settings->set('support_email', $supportEmail);
        $this->settings->set('expected_ipv4', $expectedIpv4);

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Platform settings saved</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Platform settings saved</h1>
<p>Platform settings have been updated.</p>
<p><a href="/admin/platform-settings">Back to platform settings</a></p>
</body>
</html>
HTML);
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
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
