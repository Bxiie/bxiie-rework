<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Branded public error pages.
 */
final class ErrorPage
{
    public static function notFound(string $path = ''): string
    {
        $safePath = self::escape($path);
        $message = $safePath !== '' ? "No route was found for <code>{$safePath}</code>." : 'No route was found for this request.';

        return self::page('Page not found', '404', 'This page wandered off.', $message, [
            ['/', 'Go home'],
            ['/help', 'Help'],
            ['/contact', 'Contact'],
        ]);
    }

    public static function unauthorized(string $loginPath = '/login'): string
    {
        return self::page('Sign in required', 'Unauthorized', 'You need to sign in.', 'Please sign in to continue.', [
            [$loginPath, 'Sign in'],
            ['/', 'Go home'],
            ['/help', 'Help'],
        ], $loginPath);
    }

    public static function forbidden(): string
    {
        return self::page('Access denied', '403', 'Not enough keys for this door.', 'You do not have access to this page.', [
            ['/login', 'Sign in as another user'],
            ['/', 'Go home'],
            ['/contact', 'Contact'],
        ]);
    }

    private static function page(string $title, string $eyebrow, string $heading, string $message, array $actions, ?string $redirectPath = null): string
    {
        $safeTitle = self::escape($title);
        $safeEyebrow = self::escape($eyebrow);
        $safeHeading = self::escape($heading);
        $refresh = $redirectPath ? '<meta http-equiv="refresh" content="2;url=' . self::escape($redirectPath) . '">' : '';

        $links = '';
        foreach ($actions as [$href, $label]) {
            $links .= '<a class="error-button" href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {$refresh}
    <link rel="stylesheet" href="/assets/error.css">
</head>
<body>
<main class="error-page">
    <section class="error-card">
        <a href="/" class="error-logo-link" aria-label="ArtsFolio home">
            <img src="/assets/logo_2.png" alt="ArtsFolio" class="error-logo">
        </a>
        <p class="error-eyebrow">{$safeEyebrow}</p>
        <h1>{$safeHeading}</h1>
        <p class="error-message">{$message}</p>
        <div class="error-actions">{$links}</div>
    </section>
</main>
</body>
</html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
