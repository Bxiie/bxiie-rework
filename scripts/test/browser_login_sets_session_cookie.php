<?php

/**
 * Regression test for tenant browser login session-cookie responses.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$controller = file_get_contents($root . '/app/Http/Controllers/Auth/LoginController.php');

if ($controller === false) {
    fwrite(STDERR, "Could not read LoginController.php.
");
    exit(1);
}

$requiredSnippets = [
    '$cookie = SessionCookie::issueSetCookie($token, true);',
    "'Set-Cookie' => \$cookie,",
    '$cookie = SessionCookie::expireSetCookie();',
];

foreach ($requiredSnippets as $snippet) {
    if (!str_contains($controller, $snippet)) {
        fwrite(STDERR, "Missing login-cookie regression marker: {$snippet}
");
        exit(1);
    }
}

if (preg_match("/SessionCookie::issueSetCookie\(\$token, true\);\s*FlashMessages::success\('Signed in\.'\);\s*return new Response\('', 302, \['Location' => '\/admin'\]\);/s", $controller) === 1) {
    fwrite(STDERR, "Login still creates a cookie header without returning it to the browser.
");
    exit(1);
}

echo "Browser login Set-Cookie regression test passed.
";

// End of file.
