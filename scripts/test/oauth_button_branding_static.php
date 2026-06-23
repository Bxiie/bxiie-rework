<?php
declare(strict_types=1);

/**
 * Regression checks for Google and Facebook OAuth button branding.
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
    'oauth-provider-mark-google',
    'oauth-provider-mark-facebook',
    'oauth-provider-label',
    'Continue with Google',
    'Continue with Facebook',
    'viewBox="0 0 24 24"',
] as $requiredText) {
    if (!str_contains($authContents, $requiredText)) {
        $failures[] = "AuthPage.php missing: {$requiredText}";
    }
}

foreach ([
    '/* OAuth provider branding. */',
    '.oauth-provider-mark',
    '.oauth-provider-mark svg',
    '.oauth-provider-label',
] as $requiredText) {
    if (!str_contains($cssContents, $requiredText)) {
        $failures[] = "auth.css missing: {$requiredText}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "OAuth button branding static check failed:\n - "
        . implode("\n - ", $failures)
        . "\n");
    exit(1);
}

fwrite(STDOUT, "OAuth button branding static checks passed.\n");

/* End of file. */