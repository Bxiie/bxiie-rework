<?php
declare(strict_types=1);

/**
 * Static checks for per-user administrator time-zone preferences.
 */

$root = dirname(__DIR__, 2);

$checks = [
    'database/migrations/0047_user_timezones.sql' => [
        "ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'UTC'",
    ],
    'app/Support/Time/UserTimezoneContext.php' => [
        'date_default_timezone_set($timezone);',
        'SET time_zone = ',
        'DateTimeZone::listIdentifiers()',
    ],
    'app/Http/Controllers/Auth/UserTimezoneController.php' => [
        'action="/account/timezone"',
        'Stored timestamps remain UTC.',
    ],
    'app/Platform/Identity/UserRepository.php' => [
        'public function updateTimezone(',
    ],
    'app/Platform/Auth/Session/SessionRepository.php' => [
        'u.timezone',
    ],
    'app/Http/AppKernel.php' => [
        'UserTimezoneContext::apply($pdo, $currentUser);',
    ],
    'app/Http/Routes/platform.php' => [
        "\$router->get('/account/timezone'",
        "\$router->post('/account/timezone'",
    ],
    'app/Http/Routes/tenant.php' => [
        "\$router->get('/account/timezone'",
        "\$router->post('/account/timezone'",
    ],
    'app/Http/View/AdminLayout.php' => [
        '<a href="/account/timezone">Time zone</a>',
    ],
    'app/Http/View/TenantAdminLayout.php' => [
        '<a href="/account/timezone">Time zone</a>',
    ],
];

$failures = [];

foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    $contents = is_file($path) ? file_get_contents($path) : false;

    if ($contents === false) {
        $failures[] = "Missing or unreadable {$relative}";
        continue;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = "{$relative} missing {$needle}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "User time-zone preference static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "User time-zone preference static checks passed.\n");

/* End of file. */