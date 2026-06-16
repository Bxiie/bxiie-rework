<?php

declare(strict_types=1);

/**
 * Static regression checks for the split CAPTCHA model.
 *
 * Platform forms continue to use Cloudflare Turnstile.
 * Tenant public forms use ArtsFolio's built-in first-party CAPTCHA so tenant
 * subdomains and custom domains are not constrained by Turnstile hostname
 * registration limits.
 */

$platformSettingsPath = __DIR__ . '/../../app/Http/Controllers/Platform/Admin/SettingsController.php';
$tenantSettingsPath = __DIR__ . '/../../app/Http/Controllers/Tenant/Admin/SettingsController.php';
$tenantContactPath = __DIR__ . '/../../app/Http/Controllers/Tenant/ContactController.php';
$tenantHomePath = __DIR__ . '/../../app/Http/Controllers/Tenant/HomeController.php';
$tenantSignupPath = __DIR__ . '/../../app/Http/Controllers/Tenant/SignupController.php';

$platformSettings = file_get_contents($platformSettingsPath);
$tenantSettings = file_get_contents($tenantSettingsPath);
$tenantContact = file_get_contents($tenantContactPath);
$tenantHome = file_get_contents($tenantHomePath);
$tenantSignup = file_get_contents($tenantSignupPath);

foreach ([
    $platformSettingsPath => $platformSettings,
    $tenantSettingsPath => $tenantSettings,
    $tenantContactPath => $tenantContact,
    $tenantHomePath => $tenantHome,
    $tenantSignupPath => $tenantSignup,
] as $path => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Could not read {$path}\n");
        exit(1);
    }
}

$platformChecks = [
    'platform settings still expose Turnstile site key' => 'turnstile_site_key',
    'platform settings still expose Turnstile secret key' => 'turnstile_secret_key',
];

foreach ($platformChecks as $label => $needle) {
    if (!str_contains($platformSettings, $needle)) {
        fwrite(STDERR, "Failed static regression check: {$label}\n");
        exit(1);
    }
}

$tenantSettingsForbidden = [
    'tenant settings must not expose Turnstile site key' => 'turnstile_site_key',
    'tenant settings must not expose Turnstile secret key' => 'turnstile_secret_key',
    'tenant settings must not render Cloudflare widget config' => 'cf-turnstile',
];

foreach ($tenantSettingsForbidden as $label => $needle) {
    if (str_contains($tenantSettings, $needle)) {
        fwrite(STDERR, "Failed static regression check: {$label}\n");
        exit(1);
    }
}

$tenantCaptchaChecks = [
    'tenant contact uses first-party captcha' => [$tenantContact, 'FirstPartyCaptcha'],
    'tenant home renders first-party captcha' => [$tenantHome, 'FirstPartyCaptcha'],
    'tenant signup uses first-party captcha' => [$tenantSignup, 'FirstPartyCaptcha'],
];

foreach ($tenantCaptchaChecks as $label => [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Failed static regression check: {$label}\n");
        exit(1);
    }
}

echo "Turnstile/platform and tenant CAPTCHA static checks passed.\n";

// End of file.
