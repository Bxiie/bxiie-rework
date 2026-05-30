<?php

/**
 * Shared browser session cookie helper.
 */

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Middleware\CurrentUser;

/**
 * Issues browser-session cookies consistently for platform and artsfol.io
 * subdomain flows. Custom domains cannot share cookies with artsfol.io; those
 * flows must go through OAuth/redirect based sign-in handoff.
 */
final class SessionCookie
{
    public static function issueHeader(string $token, bool $persistent = true): string
    {
        $parts = [
            CurrentUser::COOKIE_NAME . '=' . rawurlencode($token),
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];

        if (self::isSecure()) {
            $parts[] = 'Secure';
        }

        $parts[] = 'Max-Age=' . ($persistent ? 1209600 : 86400);
        $domain = self::cookieDomain();
        if ($domain !== '') {
            $parts[] = 'Domain=' . $domain;
        }

        return implode('; ', $parts);
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
        $parts = [
            CurrentUser::COOKIE_NAME . '=deleted',
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
            'Max-Age=0',
        ];

        if (self::isSecure()) {
            $parts[] = 'Secure';
        }

        $domain = self::cookieDomain();
        if ($domain !== '') {
            $parts[] = 'Domain=' . $domain;
        }

        return implode('; ', $parts);
    }

    /**
     * Backward-compatible alias for controllers that still call the older
     * cookie helper method name.
     */
    public static function expireSetCookie(): string
    {
        return self::expireHeader();
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
