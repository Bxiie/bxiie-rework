<?php
declare(strict_types=1);

/**
 * Regression checks for compact Google and Facebook OAuth branding.
 */

$projectRoot = dirname(__DIR__, 2);
$authPagePath = $projectRoot . '/app/Http/View/AuthPage.php';
$cssPath = $projectRoot . '/public/assets/auth.css';

$authContents = file_get_contents($authPagePath);
$cssContents = file_get_contents($cssPath);

if ($authContents === false || $cssContents === false) {
    fwrite(STDERR, "OAuth button branding static check failed: unable to read source files.\n");
    exit(1);
}

$failures = [];

foreach ([
    'oauth-button-google',
    'oauth-button-facebook',
    'oauth-provider-icon-google',
    'oauth-provider-icon-facebook',
    'oauth-provider-label',
    'Continue with Google',
    'Continue with Facebook',
    'width="18" height="18"',
    'style="display:block;width:18px;height:18px"',
] as $requiredText) {
    if (!str_contains($authContents, $requiredText)) {
        $failures[] = "AuthPage.php missing: {$requiredText}";
    }
}

foreach ([
    '/* Provider branding for OAuth login actions. */',
    '/* Canonical compact OAuth provider icons. */',
    '.oauth-provider-icon',
    '.oauth-provider-icon svg',
    'flex: 0 0 18px !important;',
    'height: 18px !important;',
    'width: 18px !important;',
    '/* OAuth icon-to-label spacing. */',
    'margin-right: 0.6rem !important;',
] as $requiredText) {
    if (!str_contains($cssContents, $requiredText)) {
        $failures[] = "auth.css missing: {$requiredText}";
    }
}

if (str_contains($authContents, 'oauth-provider-mark-google')
    || str_contains($authContents, 'oauth-provider-mark-facebook')
) {
    $failures[] = 'AuthPage.php still contains obsolete duplicate provider marks.';
}

if (str_contains($cssContents, '/* OAuth provider branding. */')
    || str_contains($cssContents, '/* Compact OAuth provider branding. */')
) {
    $failures[] = 'auth.css still contains obsolete duplicate OAuth CSS blocks.';
}

if (substr_count($authContents, 'oauth-provider-icon-google') !== 1) {
    $failures[] = 'Google OAuth button must contain exactly one provider icon.';
}

if (substr_count($authContents, 'oauth-provider-icon-facebook') !== 1) {
    $failures[] = 'Facebook OAuth button must contain exactly one provider icon.';
}

if (substr_count($authContents, 'width="18" height="18"') !== 2) {
    $failures[] = 'Both OAuth SVGs must have intrinsic 18 by 18 dimensions.';
}

if ($failures !== []) {
    fwrite(
        STDERR,
        "OAuth button branding static check failed:\n - "
        . implode("\n - ", $failures)
        . "\n"
    );
    exit(1);
}

fwrite(STDOUT, "OAuth button branding static checks passed.\n");

/* End of file. */