<?php

/**
 * Shared browser-session cookie helper.
 */

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Middleware\CurrentUser;

/**
 * Builds the session cookie consistently for platform, tenant subdomain, and
 * tenant custom-domain login flows.
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

        $maxAge = self::maxAge($persistent);
        if ($maxAge > 0) {
            $parts[] = 'Max-Age=' . $maxAge;
        }

        $domain = self::cookieDomain();
        if ($domain !== '') {
            $parts[] = 'Domain=' . $domain;
        }

        return implode('; ', $parts);
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

    public static function issueSetCookie(string $token, bool $persistent = true): void
    {
        $options = [
            'expires' => time() + self::maxAge($persistent),
            'path' => '/',
            'secure' => self::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $domain = self::cookieDomain();
        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        setcookie(CurrentUser::COOKIE_NAME, $token, $options);
    }

    public static function expireSetCookie(): void
    {
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => self::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $domain = self::cookieDomain();
        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        setcookie(CurrentUser::COOKIE_NAME, '', $options);
    }

    private static function maxAge(bool $persistent): int
    {
        if (!$persistent) {
            return 86400;
        }

        $days = (int) (getenv('ARTSFOLIO_PERSISTENT_LOGIN_DAYS') ?: getenv('PERSISTENT_LOGIN_DAYS') ?: 14);
        $days = max(1, min(365, $days));

        return $days * 86400;
    }

    private static function cookieDomain(): string
    {
        $configured = trim((string) (getenv('ARTSFOLIO_SESSION_COOKIE_DOMAIN') ?: getenv('SESSION_COOKIE_DOMAIN') ?: ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
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
