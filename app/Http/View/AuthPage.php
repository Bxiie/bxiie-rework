<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Branded ArtsFolio authentication pages.
 */
final class AuthPage
{
    public static function login(string $action = '/login', string $message = ''): string
    {
        $safeAction = self::escape($action);
        $notice = $message !== '' ? '<p class="auth-notice">' . self::escape($message) . '</p>' : '';

        return self::page('Sign in', <<<HTML
<p class="auth-eyebrow">Welcome back</p>
<h1>Sign in to ArtsFolio</h1>
<p class="auth-copy">Manage your artist site, artwork catalog, messages, email list, analytics, discovery settings, and public content.</p>
{$notice}
<div class="sso-row">
    <a href="/auth/google">Continue with Google</a>
    <a href="/auth/facebook">Continue with Facebook</a>
</div>
<div class="divider"><span>or use email</span></div>
<form method="post" action="{$safeAction}" class="auth-form">
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button type="submit">Sign in</button>
</form>
<p class="auth-links"><a href="/password/forgot">Forgot password?</a><a href="/signup">Create an account</a><a href="/help">Need help?</a></p>
HTML);
    }

    public static function register(string $action = '/register'): string
    {
        $safeAction = self::escape($action);

        return self::page('Create account', <<<HTML
<p class="auth-eyebrow">Start your site</p>
<h1>Create your ArtsFolio account</h1>
<p class="auth-copy">Use SSO for the fastest start, or create a local account with email and password.</p>
<div class="sso-row">
    <a href="/auth/google">Continue with Google</a>
    <a href="/auth/facebook">Continue with Facebook</a>
</div>
<div class="divider"><span>or use email</span></div>
<form method="post" action="{$safeAction}" class="auth-form">
    <label>Name<input name="name" autocomplete="name"></label>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="new-password" required></label>
    <button type="submit">Create account</button>
</form>
<p class="auth-links"><a href="/login">Already have an account?</a><a href="/help">Setup guide</a></p>
HTML);
    }

    public static function forgotPassword(string $action = '/password/forgot'): string
    {
        $safeAction = self::escape($action);

        return self::page('Reset password', <<<HTML
<p class="auth-eyebrow">Password reset</p>
<h1>Reset your password</h1>
<p class="auth-copy">Enter your email address and we will send reset instructions if an account exists for that address.</p>
<form method="post" action="{$safeAction}" class="auth-form">
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <button type="submit">Send reset link</button>
</form>
<p class="auth-links"><a href="/login">Back to login</a></p>
HTML);
    }

    private static function page(string $title, string $body): string
    {
        $safeTitle = self::escape($title);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
<main class="auth-page">
    <section class="auth-card">
        <a href="/" class="auth-logo-link" aria-label="ArtsFolio home">
            <img src="/assets/logo_2.png" alt="ArtsFolio" class="auth-logo">
        </a>
        {$body}
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
