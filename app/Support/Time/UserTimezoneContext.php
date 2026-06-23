<?php

declare(strict_types=1);

namespace App\Support\Time;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Applies the signed-in user's preferred IANA time zone for this request.
 *
 * Database timestamps remain UTC. MariaDB converts TIMESTAMP values for the
 * current connection; PHP date formatting uses the same user preference.
 */
final class UserTimezoneContext
{
    public const DEFAULT_TIMEZONE = 'UTC';

    public static function normalize(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        if ($timezone === '') {
            return self::DEFAULT_TIMEZONE;
        }

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            return self::DEFAULT_TIMEZONE;
        }

        return $timezone;
    }

    public static function apply(PDO $pdo, ?array $currentUser): string
    {
        $timezone = self::normalize(
            isset($currentUser['timezone']) ? (string) $currentUser['timezone'] : null
        );

        date_default_timezone_set($timezone);
        $GLOBALS['artsfolio_user_timezone'] = $timezone;

        try {
            $pdo->exec('SET time_zone = ' . $pdo->quote($timezone));
        } catch (Throwable) {
            $offset = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('P');
            $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
        }

        return $timezone;
    }

    /** @return list<string> */
    public static function identifiers(): array
    {
        return DateTimeZone::listIdentifiers();
    }
}

// End of file.