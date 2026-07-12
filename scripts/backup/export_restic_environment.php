<?php

declare(strict_types=1);

/**
 * Exports Restic and Backblaze credentials from platform settings as a
 * shell-sourceable environment file. Output must be redirected to a root-only
 * temporary file and removed immediately after the calling job loads it.
 */

use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$settings = new PlatformSettingsRepository(Database::connect($root));
$values = [
    'RESTIC_REPOSITORY' => trim((string) $settings->get('restic_repository', '')),
    'RESTIC_PASSWORD' => (string) $settings->get('restic_password', ''),
    'B2_ACCOUNT_ID' => (string) $settings->get('b2_account_id', ''),
    'B2_ACCOUNT_KEY' => (string) $settings->get('b2_account_key', ''),
    'ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET' => trim((string) $settings->get('restic_weekly_read_subset', '5%')),
];

foreach (['RESTIC_REPOSITORY', 'RESTIC_PASSWORD', 'B2_ACCOUNT_ID', 'B2_ACCOUNT_KEY'] as $required) {
    if ($values[$required] === '') {
        fwrite(STDERR, "Missing required Platform Admin backup setting: {$required}\n");
        exit(1);
    }
}

if (preg_match('/^b2:[^:\\s]+:.+$/', $values['RESTIC_REPOSITORY']) !== 1) {
    fwrite(STDERR, "RESTIC_REPOSITORY must use the b2:bucket:path format.\n");
    exit(1);
}

if (preg_match('/^(?:100|[1-9]?[0-9])%$/', $values['ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET']) !== 1) {
    fwrite(STDERR, "ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET must be a percentage from 0% through 100%.\n");
    exit(1);
}

foreach ($values as $name => $value) {
    echo 'export ' . $name . '=' . shellQuote($value) . PHP_EOL;
}

/** Quote a value safely for POSIX-compatible shell source operations. */
function shellQuote(string $value): string
{
    return "'" . str_replace("'", "'\\''", $value) . "'";
}

// End of file.
