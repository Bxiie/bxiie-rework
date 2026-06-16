<?php

declare(strict_types=1);

/**
 * Static regression checks for tenant first-party CAPTCHA behavior.
 */

$root = dirname(__DIR__, 2);
$captcha = file_get_contents($root . '/app/Services/FirstPartyCaptcha.php');
$home = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php');
$contact = file_get_contents($root . '/app/Http/Controllers/Tenant/ContactController.php');
$signup = file_get_contents($root . '/app/Http/Controllers/Tenant/SignupController.php');
$tenantSettings = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$platformMarketing = file_get_contents($root . '/app/Http/Controllers/Platform/MarketingController.php');

foreach ([
    'FirstPartyCaptcha.php' => $captcha,
    'Tenant HomeController.php' => $home,
    'Tenant ContactController.php' => $contact,
    'Tenant SignupController.php' => $signup,
    'Tenant Admin SettingsController.php' => $tenantSettings,
    'Platform MarketingController.php' => $platformMarketing,
] as $label => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Could not read {$label}\n");
        exit(1);
    }
}

$captchaChecks = [
    'first party wrapper' => 'data-af-captcha',
    'first party token' => 'af_captcha_token',
    'first party checkbox' => 'af_captcha_confirm',
    'honeypot field' => 'website_url',
    'minimum dwell time' => 'MIN_DWELL_SECONDS',
    'single use token removal' => 'unset($_SESSION[self::SESSION_KEY][$token]);',
    'Turnstile still supported' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
];

foreach ($captchaChecks as $label => $needle) {
    if (!str_contains($captcha, $needle)) {
        fwrite(STDERR, "Missing tenant custom CAPTCHA service check: {$label}\n");
        exit(1);
    }
}

foreach ([
    'tenant home does not use Turnstile site key' => [$home, 'private function turnstileSiteKey(TenantContext $tenant): string'],
    'tenant contact does not use Turnstile secret' => [$contact, 'private function turnstileSecretKey(TenantContext $tenant): string'],
    'tenant signup does not use Turnstile secret' => [$signup, 'private function turnstileSecretKey(TenantContext $tenant): string'],
    'tenant settings explain built-in captcha' => [$tenantSettings, 'built-in ArtsFolio CAPTCHA'],
    'platform marketing still loads Turnstile script' => [$platformMarketing, 'https://challenges.cloudflare.com/turnstile/v0/api.js'],
] as $label => [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Missing tenant custom CAPTCHA wiring check: {$label}\n");
        exit(1);
    }
}

echo "Tenant custom CAPTCHA static checks passed.\n";

// End of file.
