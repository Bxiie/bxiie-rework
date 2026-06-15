<?php

declare(strict_types=1);

/**
 * Static regression checks for Cloudflare Turnstile form protection.
 */

$root = dirname(__DIR__, 2);

$checks = [
    'Turnstile helper posts to Cloudflare Siteverify' => [
        'file' => $root . '/app/Services/FirstPartyCaptcha.php',
        'needle' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ],
    'Turnstile helper reads Cloudflare response field' => [
        'file' => $root . '/app/Services/FirstPartyCaptcha.php',
        'needle' => "cf-turnstile-response",
    ],
    'Platform pages load Turnstile client script' => [
        'file' => $root . '/app/Http/Controllers/Platform/MarketingController.php',
        'needle' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
    ],
    'Tenant pages load Turnstile client script' => [
        'file' => $root . '/app/Http/Controllers/Tenant/HomeController.php',
        'needle' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
    ],
    'Platform settings use Turnstile keys' => [
        'file' => $root . '/app/Http/Controllers/Platform/Admin/SettingsController.php',
        'needle' => 'turnstile_secret_key',
    ],
    'Tenant settings use Turnstile keys' => [
        'file' => $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php',
        'needle' => 'turnstile_secret_key',
    ],
];

foreach ($checks as $name => $check) {
    $content = file_get_contents($check['file']);
    if ($content === false || !str_contains($content, $check['needle'])) {
        fwrite(STDERR, "Failed static regression check: {$name}\n");
        exit(1);
    }
}

foreach ([
    $root . '/app/Http/Controllers/Platform/MarketingController.php',
    $root . '/app/Http/Controllers/Tenant/HomeController.php',
    $root . '/app/Views/public/layout.php',
] as $file) {
    $content = file_get_contents($file);
    if ($content !== false && str_contains($content, 'google.com/recaptcha')) {
        fwrite(STDERR, "Failed static regression check: Google reCAPTCHA script remains in {$file}\n");
        exit(1);
    }
}

echo "Turnstile CAPTCHA static checks passed.\n";

// End of file.
