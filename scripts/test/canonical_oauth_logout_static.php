<?php

declare(strict_types=1);

/**
 * Static regression checks for canonical platform OAuth and GET-safe logout.
 */

$oauthPath = __DIR__ . '/../../app/Http/Controllers/Auth/OAuthController.php';
$authPagePath = __DIR__ . '/../../app/Http/View/AuthPage.php';
$indexPath = __DIR__ . '/../../public/index.php';

$oauth = file_get_contents($oauthPath);
$authPage = file_get_contents($authPagePath);
$index = file_get_contents($indexPath);

foreach ([
    $oauthPath => $oauth,
    $authPagePath => $authPage,
    $indexPath => $index,
] as $path => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Could not read {$path}\n");
        exit(1);
    }
}

$checks = [
    'OAuth accepts trusted absolute return_to URLs' => [$oauth, 'isTrustedReturnHost'],
    'OAuth allows artsfol.io tenant subdomains' => [$oauth, "str_ends_with(\$host, '.artsfol.io')"],
    'OAuth validates active custom tenant domains' => [$oauth, 'FROM tenant_domains'],
    'OAuth requires HTTPS for absolute return_to URLs' => [$oauth, "\$scheme !== 'https'"],
    'Tenant login computes canonical platform OAuth links' => [$authPage, 'canonicalSocialAuthUrl'],
    'Tenant login targets platform OAuth host' => [$authPage, 'https://artsfol.io/auth/'],
    'Tenant auth redirect closure exists' => [$index, '$tenantOauthRedirect'],
    'Tenant Google auth route uses redirect closure' => [$index, "\$tenantOauthRedirect(\$request, 'google')"],
    'Tenant Facebook auth route uses redirect closure' => [$index, "\$tenantOauthRedirect(\$request, 'facebook')"],
    'Tenant auth redirect targets platform OAuth host' => [$index, 'https://artsfol.io/auth/'],
    'GET logout route is registered' => [$index, "get('/logout'"],
];

foreach ($checks as $label => [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Failed canonical OAuth/logout static check: {$label}\n");
        exit(1);
    }
}

if (substr_count($authPage, 'class="sso-row"') !== 1) {
    fwrite(STDERR, "Failed canonical OAuth/logout static check: AuthPage should render exactly one SSO row\n");
    exit(1);
}

echo "Canonical OAuth/logout static checks passed.\n";

// End of file.
