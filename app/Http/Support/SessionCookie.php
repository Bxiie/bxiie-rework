<?php

/**
 * Shared browser session cookie helper.
 */

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Middleware\CurrentUser;

/**
 * Builds Set-Cookie header values for browser authentication.
 *
 * This class keeps the old issueSetCookie()/expireSetCookie() method names as
 * aliases because some deployed controllers still call those names during
 * rolling deploys. Removing the aliases turns /login into a production 500.
 */
final class SessionCookie
{
    public static function issueHeader(string $token, bool $persistent = true): string
    {
        return self::build(CurrentUser::COOKIE_NAME . '=' . rawurlencode($token), $persistent ? 1209600 : 86400, true);
    }

    public static function issueSetCookie(string $token, bool $persistent = true): string
    {
        return self::issueHeader($token, $persistent);
    }

    /**
     * Returns every Set-Cookie header required for login.
     *
     * Older controllers used this plural method name. Keep it as a safe alias
     * so rolling deploys do not break /login while cookie clearing evolves.
     *
     * @return list<string>
     */
    public static function issueHeaders(string $token, bool $persistent = true): array
    {
        return self::loginHeaders($token, $persistent);
    }

    public static function expireHeader(): string
    {
        return self::build(CurrentUser::COOKIE_NAME . '=deleted', 0, true);
    }

    public static function expireSetCookie(): string
    {
        return self::expireHeader();
    }

    /**
     * Returns every Set-Cookie header required for logout.
     *
     * @return list<string>
     */
    public static function expireHeaders(): array
    {
        return self::logoutHeaders();
    }

    /**
     * Returns all cookie headers needed to clear stale host-only/domain cookies
     * and then issue the current valid session cookie.
     *
     * @return list<string>
     */
    public static function loginHeaders(string $token, bool $persistent = true): array
    {
        return array_merge(self::logoutHeaders(), [self::issueHeader($token, $persistent)]);
    }

    /**
     * Returns cookie headers for both the configured domain and host-only case.
     * This avoids old broken cookies shadowing the working cookie in browsers.
     *
     * @return list<string>
     */
    public static function logoutHeaders(): array
    {
        $headers = [self::build(CurrentUser::COOKIE_NAME . '=deleted', 0, false)];
        $domain = self::cookieDomain();
        if ($domain !== '') {
            $headers[] = self::build(CurrentUser::COOKIE_NAME . '=deleted', 0, true);
        }

        return array_values(array_unique($headers));
    }

    private static function build(string $nameValue, int $maxAge, bool $includeDomain): string
    {
        $parts = [
            $nameValue,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
            'Max-Age=' . $maxAge,
        ];

        if (self::isSecure()) {
            $parts[] = 'Secure';
        }

        $domain = self::cookieDomain();
        if ($includeDomain && $domain !== '') {
            $parts[] = 'Domain=' . $domain;
        }

        return implode('; ', $parts);
    }

    private static function cookieDomain(): string
    {
        $configured = trim((string) (getenv('ARTSFOLIO_SESSION_COOKIE_DOMAIN') ?: ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = explode(':', $host, 2)[0];
        if ($host === 'artsfol.io' || str_ends_with($host, '.artsfol.io')) {
            return '.artsfol.io';
        }

        return '';
    }

    private static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}

// End of file.
