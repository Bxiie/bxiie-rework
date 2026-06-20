<?php

/**
 * Branded browser-facing error page renderer.
 */

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Tenancy\TenantContext;
use Throwable;

/**
 * Renders every browser-facing error response inside platform or tenant chrome.
 *
 * The renderer intentionally avoids database dependencies so it can be used from
 * exception and shutdown handlers when the application is already in trouble.
 */
final class ErrorPage
{
    /**
     * Builds a branded 404 page without leaking internal route details.
     */
    public static function notFound(string $message = 'The page you requested could not be found.'): string
    {
        return self::page(
            title: 'Page not found',
            statusLabel: '404',
            heading: 'This page wandered off.',
            message: $message,
            actions: self::defaultActions(),
        );
    }

    /**
     * Builds a branded 401/403-style page for sign-in failures.
     */
    public static function unauthorized(string $loginPath = '/login', string $message = 'Please sign in to continue.'): string
    {
        return self::page(
            title: 'Sign in required',
            statusLabel: 'Access',
            heading: 'You need to sign in.',
            message: $message,
            actions: [
                [$loginPath, 'Sign in'],
                [self::homeUrl(), 'Go home'],
                [self::supportUrl(), 'Help'],
            ],
        );
    }

    /**
     * Builds a branded forbidden page.
     */
    public static function forbidden(string $message = 'You do not have access to this page.'): string
    {
        return self::page(
            title: 'Access denied',
            statusLabel: '403',
            heading: 'Not enough keys for this door.',
            message: $message,
            actions: [
                ['/login', 'Sign in'],
                [self::homeUrl(), 'Go home'],
                [self::supportUrl(), 'Contact'],
            ],
        );
    }

    /**
     * Builds a branded error page for known HTTP status codes.
     */
    public static function status(int $statusCode, string $message = ''): string
    {
        $copy = self::statusCopy($statusCode);

        return self::page(
            title: $copy['title'],
            statusLabel: (string) $statusCode,
            heading: $copy['heading'],
            message: $message !== '' ? $message : $copy['message'],
            actions: self::defaultActions(),
        );
    }

    /**
     * Builds a branded 500 page that hides exception details from visitors.
     */
    public static function serverError(string $message = 'The page could not be loaded. The issue has been logged.'): string
    {
        return self::page(
            title: 'Something went wrong',
            statusLabel: '500',
            heading: 'Something went wrong.',
            message: $message,
            actions: self::defaultActions(),
        );
    }

    /**
     * Emits a branded exception page and logs the underlying throwable.
     */
    public static function sendException(Throwable $throwable): void
    {
        error_log((string) $throwable);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo self::serverError();
    }

    /**
     * Emits a branded fatal-error page for shutdown-handler failures.
     */
    public static function sendFatal(array $error): void
    {
        error_log('Fatal browser-facing error: ' . json_encode($error));

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo self::serverError();
    }

    /**
     * Returns the current tenant context recorded by the front controller.
     */
    private static function tenant(): ?TenantContext
    {
        $tenant = $GLOBALS['artsfolio_tenant_context'] ?? null;

        return $tenant instanceof TenantContext ? $tenant : null;
    }

    /**
     * Returns true when the current request is being served as platform chrome.
     */
    private static function isPlatform(): bool
    {
        if (!empty($GLOBALS['artsfolio_platform_context'])) {
            return true;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return in_array($host, ['artsfol.io', 'www.artsfol.io', 'app.artsfol.io'], true)
            || str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? '/'), '/platform');
    }

    /**
     * Returns brand values without using the database or tenant settings.
     */
    private static function brand(): array
    {
        $tenant = self::tenant();
        if ($tenant !== null && !self::isPlatform()) {
            return [
                'name' => $tenant->name !== '' ? $tenant->name : $tenant->slug,
                'home_url' => '/',
                'logo_url' => '/assets/logo_2.png',
                'footer' => 'Powered by ArtsFolio',
                'body_class' => 'tenant-error-page',
            ];
        }

        return [
            'name' => 'ArtsFolio',
            'home_url' => 'https://artsfol.io/',
            'logo_url' => '/assets/logo_2.png',
            'footer' => 'ArtsFolio',
            'body_class' => 'platform-error-page',
        ];
    }

    /**
     * Renders the branded error shell.
     */
    private static function page(string $title, string $statusLabel, string $heading, string $message, array $actions): string
    {
        $brand = self::brand();
        $safeTitle = self::escape($title);
        $safeStatus = self::escape($statusLabel);
        $safeHeading = self::escape($heading);
        $safeMessage = self::escape($message);
        $safeBrand = self::escape((string) $brand['name']);
        $safeLogoUrl = self::escape((string) $brand['logo_url']);
        $safeHomeUrl = self::escape((string) $brand['home_url']);
        $safeFooter = self::escape((string) $brand['footer']);
        $safeBodyClass = self::escape((string) $brand['body_class']);

        $links = '';
        foreach ($actions as [$href, $label]) {
            $links .= '<a class="error-button" href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | {$safeBrand}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/error.css">
</head>
<body class="{$safeBodyClass}">
<main class="error-page">
    <section class="error-card" role="alert">
        <a href="{$safeHomeUrl}" class="error-logo-link" aria-label="{$safeBrand} home">
            <img src="{$safeLogoUrl}" alt="{$safeBrand}" class="error-logo">
        </a>
        <p class="error-brand">{$safeBrand}</p>
        <p class="error-eyebrow">{$safeStatus}</p>
        <h1>{$safeHeading}</h1>
        <p class="error-message">{$safeMessage}</p>
        <div class="error-actions">{$links}</div>
        <p class="error-footer">{$safeFooter}</p>
    </section>
</main>
</body>
</html>
HTML;
    }

    /**
     * Returns default actions for the active brand context.
     */
    private static function defaultActions(): array
    {
        return [
            [self::homeUrl(), 'Go home'],
            [self::supportUrl(), 'Help'],
        ];
    }

    private static function homeUrl(): string
    {
        return (string) self::brand()['home_url'];
    }

    private static function supportUrl(): string
    {
        return self::tenant() !== null && !self::isPlatform() ? '/contact' : '/help';
    }

    /**
     * Returns visitor-safe copy for common HTTP errors.
     */
    private static function statusCopy(int $statusCode): array
    {
        return match ($statusCode) {
            400 => [
                'title' => 'Bad request',
                'heading' => 'That request was not understood.',
                'message' => 'Please check the address and try again.',
            ],
            401 => [
                'title' => 'Sign in required',
                'heading' => 'You need to sign in.',
                'message' => 'Please sign in to continue.',
            ],
            403 => [
                'title' => 'Access denied',
                'heading' => 'Not enough keys for this door.',
                'message' => 'You do not have access to this page.',
            ],
            419 => [
                'title' => 'Security check expired',
                'heading' => 'The security check expired.',
                'message' => 'Please go back, refresh the page, and try again.',
            ],
            404 => [
                'title' => 'Page not found',
                'heading' => 'This page wandered off.',
                'message' => 'The page you requested could not be found.',
            ],
            502 => [
                'title' => 'Temporary platform issue',
                'heading' => 'The platform hit a rough patch.',
                'message' => 'Please try again in a few minutes.',
            ],
            503 => [
                'title' => 'Temporarily unavailable',
                'heading' => 'This page is temporarily unavailable.',
                'message' => 'Please check back later.',
            ],
            504 => [
                'title' => 'Request timed out',
                'heading' => 'That request took too long.',
                'message' => 'Please try again.',
            ],
            default => [
                'title' => 'Something went wrong',
                'heading' => 'Something went wrong.',
                'message' => 'The request could not be completed.',
            ],
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
