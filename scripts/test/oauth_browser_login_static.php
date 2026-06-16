#!/usr/bin/php
<?php

/**
 * Static regression checks for completed Google/Facebook browser OAuth login.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'app/Http/Controllers/Auth/OAuthController.php' => [
        'consumeState',
        'exchangeCodeForToken',
        'fetchProviderProfile',
        'findOrCreateUser',
        'SessionCookie::loginHeaders',
        'https://oauth2.googleapis.com/token',
        'https://openidconnect.googleapis.com/v1/userinfo',
        'https://graph.facebook.com/v19.0/oauth/access_token',
        'https://graph.facebook.com/v19.0/me',
        "provider . '_oauth_client_id'",
        "provider . '_oauth_client_secret'",
        'Missing or invalid OAuth state',
        'Provider profile did not include a valid email address',
    ],
    'public/index.php' => [
        'new OAuthController($pdo)',
        "/auth/google/callback",
        "/auth/facebook/callback",
    ],
    'docs/dev/oauth-provider-guide.md' => [
        'callback token exchange and user/session creation are implemented',
        'oauth_auth_base_url',
    ],
    'docs/admin/social-auth.md' => [
        'Social login is implemented',
        'https://artsfol.io/auth/google/callback',
        'https://artsfol.io/auth/facebook/callback',
        'OAuth callback base URL',
    ],
    'PROJECT_STATE.md' => [
        'Implemented Google and Facebook browser OAuth login callbacks',
    ],
];

foreach ($files as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }

    $text = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            fwrite(STDERR, "{$file} missing {$needle}\n");
            exit(1);
        }
    }
}

$controller = file_get_contents($root . '/app/Http/Controllers/Auth/OAuthController.php');
if (str_contains($controller, 'OAuth callback pending') || str_contains($controller, 'return Response::html(\'<h1>OAuth callback pending')) {
    fwrite(STDERR, "OAuth callbacks still contain the old pending implementation.\n");
    exit(1);
}

if (!preg_match('/unset\(\$_SESSION\[self::SESSION_STATE_KEY\]\[\$state\]\)/', $controller)) {
    fwrite(STDERR, "OAuth state is not consumed exactly once.\n");
    exit(1);
}

if (str_contains($controller, 'ARTSFOLIO_GOOGLE_CLIENT_ID') || str_contains($controller, 'ARTSFOLIO_FACEBOOK_CLIENT_ID') || str_contains($controller, 'ARTSFOLIO_AUTH_BASE_URL')) {
    fwrite(STDERR, "OAuth browser login must read provider settings from platform_settings, not artsfolio.env.\n");
    exit(1);
}

$settingsController = file_get_contents($root . '/app/Http/Controllers/Platform/Admin/SettingsController.php');
foreach (['google_oauth_client_id', 'google_oauth_client_secret', 'facebook_oauth_client_id', 'facebook_oauth_client_secret', 'oauth_auth_base_url'] as $settingKey) {
    if (!str_contains($settingsController, $settingKey)) {
        fwrite(STDERR, "Platform settings form missing {$settingKey}.\n");
        exit(1);
    }
}

echo "Google/Facebook browser OAuth login implementation is wired through platform_settings and no longer fail-closed pending code.\n";

// End of file.
