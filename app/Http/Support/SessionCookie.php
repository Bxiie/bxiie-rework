<?php

/**
 * Shared browser-session cookie helper.
 */

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Middleware\CurrentUser;

/**
 * Issues browser-session cookies consistently for platform and tenant domains.
 *
 * Login intentionally sends multiple Set-Cookie lines: first expire stale
 * host-only/domain variants left by older auth code, then set the canonical
 * cookie. This prevents PHP from reading an old duplicate artsfolio_session
 * value when a browser sends both host-only and parent-domain cookies.
 */
final class SessionCookie
{
    public static function issueHeader(string $token, bool $persistent = true): string
    {
        return self::buildHeader(rawurlencode($token), $persistent ? 1209600 : 86400, self::cookieDomain());
    }

    /**
     * Returns every Set-Cookie header needed for a clean login response.
     *
     * @return array<int, string>
     */
    public static function issueHeaders(string $token, bool $persistent = true): array
    {
        $headers = self::expireHeaders();
        $headers[] = self::issueHeader($token, $persistent);

        return array_values(array_unique($headers));
    }

    /**
     * Backward-compatible alias for controllers that still call the older
     * cookie helper method name.
     */
    public static function issueSetCookie(string $token, bool $persistent = true): string
    {
        return self::issueHeader($token, $persistent);
    }

    public static function expireHeader(): string
    {
        return self::buildHeader('deleted', 0, self::cookieDomain());
    }

    /**
     * Returns Set-Cookie headers to expire both host-only and shared-domain
     * variants of the browser session cookie.
     *
     * @return array<int, string>
     */
    public static function expireHeaders(): array
    {
        $headers = [self::buildHeader('deleted', 0, '')];
        $domain = self::cookieDomain();
        if ($domain !== '') {
            $headers[] = self::buildHeader('deleted', 0, $domain);
        }

        return array_values(array_unique($headers));
    }

    /**
     * Backward-compatible alias for controllers that still call the older
     * cookie helper method name.
     */
    public static function expireSetCookie(): string
    {
        return self::expireHeader();
    }

    private static function buildHeader(string $value, int $maxAge, string $domain): string
    {
        $parts = [
            CurrentUser::COOKIE_NAME . '=' . $value,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
            'Max-Age=' . $maxAge,
        ];

        if (self::isSecure()) {
            $parts[] = 'Secure';
        }

        if ($domain !== '') {
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
