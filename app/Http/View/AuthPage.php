<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Branded authentication pages shared by platform and tenant domains.
 */
final class AuthPage
{
    public static function login(string $action = '/login', string $message = '', string $brandName = 'ArtsFolio', string $homeUrl = '/', string $csrfToken = ''): string
    {
        $safeAction = self::escape($action);
        $safeBrand = self::escape($brandName !== '' ? $brandName : 'ArtsFolio');
        $safeHome = self::escape($homeUrl !== '' ? $homeUrl : '/');
        $safeCsrf = self::escape($csrfToken);
        $notice = $message !== '' ? '<p class="auth-notice">' . self::escape($message) . '</p>' : '';
        $csrf = $safeCsrf !== '' ? '<input type="hidden" name="csrf_token" value="' . $safeCsrf . '">' : '';

        return self::page('Sign in', <<<HTML
<p class="auth-eyebrow">Welcome back</p>
<h1>Sign in to {$safeBrand}</h1>
<p class="auth-copy">Manage artwork, content, messages, subscribers, analytics, and settings.</p>
{$notice}
<div class="sso-row"><a href="/auth/google">Continue with Google</a><a href="/auth/facebook">Continue with Facebook</a></div>
<form method="post" action="{$safeAction}" class="auth-form">
    {$csrf}
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <label class="auth-checkbox"><input type="checkbox" name="keep_me_logged_in" value="1"> Keep me logged in</label>
    <button type="submit">Sign in</button>
</form>
<p class="auth-links"><a href="/password/forgot">Forgot password?</a><a href="/signup">Create an account</a><a href="/help">Need help?</a></p>
HTML, $safeBrand, $safeHome);
    }

    public static function register(string $action = '/signup', string $csrfToken = ''): string
    {
        $safeAction = self::escape($action);
        $safeCsrf = self::escape($csrfToken);
        $csrf = $safeCsrf !== '' ? '<input type="hidden" name="csrf_token" value="' . $safeCsrf . '">' : '';
        return self::page('Create account', <<<HTML
<p class="auth-eyebrow">Start your site</p>
<h1>Create your ArtsFolio account</h1>
<p class="auth-copy">Create a tenant workspace with editable branding, tenant CSS, and admin tools.</p>
<div class="sso-row"><a href="/auth/google">Continue with Google</a><a href="/auth/facebook">Continue with Facebook</a></div>
<form method="post" action="{$safeAction}" class="auth-form">
    {$csrf}
    <label>Site name<input type="text" name="site_name" required></label>
    <label>Site slug<input type="text" name="slug" pattern="[a-z0-9-]{3,63}" required></label>
    <label>Your name<input name="admin_name" autocomplete="name"></label>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
    <button type="submit">Create site</button>
</form>
<p class="auth-links"><a href="/login">Already have an account?</a><a href="/help/getting-started">Setup guide</a></p>
HTML);
    }

    public static function forgotPassword(string $action = '/password/forgot', string $csrfToken = ''): string
    {
        $safeAction = self::escape($action);
        $safeCsrf = self::escape($csrfToken);
        $csrf = $safeCsrf !== '' ? '<input type="hidden" name="csrf_token" value="' . $safeCsrf . '">' : '';
        return self::page('Reset password', <<<HTML
<p class="auth-eyebrow">Password reset</p><h1>Reset your password</h1>
<form method="post" action="{$safeAction}" class="auth-form">{$csrf}<label>Email<input type="email" name="email" autocomplete="email" required></label><button type="submit">Send reset link</button></form>
<p class="auth-links"><a href="/login">Back to login</a></p>
HTML);
    }

    public static function pageMessage(string $title, string $message): string
    {
        return self::page($title, '<p>' . self::escape($message) . '</p><p class="auth-links"><a href="/login">Back to login</a></p>');
    }

    private static function page(string $title, string $body, string $brandName = 'ArtsFolio', string $homeUrl = '/'): string
    {
        $safeTitle = self::escape($title);
        $safeBrand = self::escape($brandName);
        $safeHome = self::escape($homeUrl);
        return <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{$safeTitle} | {$safeBrand}</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/auth.css"></head>
<body><main class="auth-page"><section class="auth-card"><a href="{$safeHome}" class="auth-logo-link"><img src="/assets/logo_2.png" alt="ArtsFolio" class="auth-logo"></a>{$body}</section></main></body></html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
