<?php

declare(strict_types=1);

/**
 * Database configuration.
 *
 * Production should set:
 *
 *   ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env
 *
 * The env file should contain KEY=VALUE lines such as:
 *
 *   DB_HOST=127.0.0.1
 *   DB_PORT=3306
 *   DB_DATABASE=artsfolio
 *   DB_USERNAME=artsfolio
 *   DB_PASSWORD=...
 */

$envFile = getenv('ARTSFOLIO_ENV_FILE') ?: '/etc/artsfolio/artsfolio.env';

if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'artsfolio',
    'username' => getenv('DB_USERNAME') ?: 'artsfolio',
    'password' => getenv('DB_PASSWORD') ?: 'artsfolio_dev',
];

// End of file.
