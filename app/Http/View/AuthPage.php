<?php

declare(strict_types=1);

namespace App\Http\View;

final class AuthPage
{
    public static function login(string $action = '/login'): string
    {
        return self::page('Sign in', <<<HTML
<p class="auth-eyebrow">Welcome back</p>
<h1>Sign in to ArtsFolio</h1>
<p class="auth-copy">Manage your portfolio, artwork, messages, subscribers, analytics, and site settings.</p>
<div class="sso-row">
    <a href="/auth/google">Continue with Google</a>
    <a href="/auth/facebook">Continue with Facebook</a>
</div>
<div class="divider"><span>or use email</span></div>
<form method="post" action="{$action}" class="auth-form">
    <label>Email<input type="email" name="email" required></label>
    <label>Password<input type="password" name="password" required></label>
    <button type="submit">Sign in</button>
</form>
HTML);
    }

    private static function page(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{$title} | ArtsFolio</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
<main class="auth-page">
<section class="auth-card">
<img src="/assets/logo_2.png" alt="ArtsFolio" class="auth-logo">
{$body}
</section>
</main>
</body>
</html>
HTML;
    }
}

// End of file.
