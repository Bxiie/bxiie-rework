<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Branded authentication pages shared by platform and tenant domains.
 */
final class AuthPage
{
    public static function login(string $action = '/login', string $message = '', string $brandName = 'ArtsFolio', string $homeUrl = '/', string $csrfToken = '', bool $showCreateAccount = true, string $oauthBaseUrl = '', string $oauthReturnTo = ''): string
    {
        $safeAction = self::escape($action);
        $safeBrand = self::escape($brandName !== '' ? $brandName : 'ArtsFolio');
        $safeHome = self::escape($homeUrl !== '' ? $homeUrl : '/');
        $safeCsrf = self::escape($csrfToken);
        $notice = $message !== '' ? '<p class="auth-notice">' . self::escape($message) . '</p>' : '';
        $csrf = $safeCsrf !== '' ? '<input type="hidden" name="csrf_token" value="' . $safeCsrf . '">' : '';
        $createAccountLink = $showCreateAccount ? '<a href="/signup">Create an account</a>' : '';
        $googleHref = self::escape(self::oauthLink('google', $oauthBaseUrl, $oauthReturnTo));
        $facebookHref = self::escape(self::oauthLink('facebook', $oauthBaseUrl, $oauthReturnTo));

        return self::page('Sign in', <<<HTML
<p class="auth-eyebrow">Welcome back</p>
<h1>Sign in to {$safeBrand}</h1>
<p class="auth-copy">Manage artwork, content, messages, subscribers, analytics, and settings.</p>
{$notice}
<form method="post" action="{$safeAction}" class="auth-form">
    {$csrf}
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <label class="auth-checkbox"><input type="checkbox" name="keep_me_logged_in" value="1"> Keep me logged in</label>
    
<div class="sso-row">
    <a class="button secondary oauth-button oauth-button-google" href="{$googleHref}"><span class="oauth-provider-icon oauth-provider-icon-google" aria-hidden="true"><svg viewBox="0 0 18 18" focusable="false"><path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.482h4.844a4.14 4.14 0 0 1-1.797 2.715v2.258h2.909c1.702-1.567 2.684-3.878 2.684-6.614Z"/><path fill="#34A853" d="M9 18c2.43 0 4.468-.806 5.956-2.181l-2.909-2.258c-.806.54-1.835.859-3.047.859-2.344 0-4.328-1.585-5.037-3.714H.956v2.332A9 9 0 0 0 9 18Z"/><path fill="#FBBC05" d="M3.963 10.706A5.41 5.41 0 0 1 3.682 9c0-.592.102-1.167.281-1.706V4.962H.956A9 9 0 0 0 0 9c0 1.452.347 2.827.956 4.038l3.007-2.332Z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.507.454 3.44 1.346l2.581-2.581C13.464.892 11.426 0 9 0A9 9 0 0 0 .956 4.962l3.007 2.332C4.672 5.165 6.656 3.58 9 3.58Z"/></svg></span><span class="oauth-provider-label">Continue with Google</span></a>
    <a class="button secondary oauth-button oauth-button-facebook" href="{$facebookHref}"><span class="oauth-provider-icon oauth-provider-icon-facebook" aria-hidden="true"><svg viewBox="0 0 18 18" focusable="false"><circle cx="9" cy="9" r="9" fill="#1877F2"/><path fill="#fff" d="M10.5 15v-5h1.68l.252-1.96H10.5V6.79c0-.567.157-.953.97-.953h1.036V4.084A13.89 13.89 0 0 0 10.996 4C9.5 4 8.476 4.913 8.476 6.59v1.45H6.783V10h1.693v5H10.5Z"/></svg></span><span class="oauth-provider-label">Continue with Facebook</span></a>
</div>

<button type="submit">Sign in</button>
</form>
<p class="auth-links"><a href="/password/forgot">Forgot password?</a>{$createAccountLink}<a href="/help">Need help?</a></p>
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


    public static function resetPassword(string $action = '/password/reset', string $token = '', string $csrfToken = '', string $message = ''): string
    {
        $safeAction = self::escape($action);
        $safeToken = self::escape($token);
        $safeCsrf = self::escape($csrfToken);
        $notice = $message !== '' ? '<p class="auth-notice">' . self::escape($message) . '</p>' : '';
        $csrf = $safeCsrf !== '' ? '<input type="hidden" name="csrf_token" value="' . $safeCsrf . '">' : '';

        return self::page('Choose a new password', <<<HTML
<p class="auth-eyebrow">Password reset</p>
<h1>Choose a new password</h1>
<p class="auth-copy">Enter a new password for your ArtsFolio account.</p>
{$notice}
<form method="post" action="{$safeAction}" class="auth-form">
    {$csrf}
    <input type="hidden" name="token" value="{$safeToken}">
    <label>New password<input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
    <label>Confirm new password<input type="password" name="password_confirm" autocomplete="new-password" minlength="10" required></label>
    <button type="submit">Reset password</button>
</form>
<p class="auth-links"><a href="/login">Back to login</a></p>
HTML);
    }

    public static function pageMessage(string $title, string $message): string
    {
        return self::page($title, '<p>' . self::escape($message) . '</p><p class="auth-links"><a href="/login">Back to login</a></p>');
    }

    private static function oauthLink(string $provider, string $oauthBaseUrl, string $returnTo): string
    {
        $base = rtrim(trim($oauthBaseUrl), '/');
        $path = '/auth/' . rawurlencode($provider);
        $url = $base !== '' ? $base . $path : $path;
        if ($returnTo !== '') {
            $url .= '?return_to=' . rawurlencode($returnTo);
        }

        return $url;
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

    private static function canonicalSocialAuthUrl(string $provider, string $host): string
    {
        $provider = strtolower(trim($provider));
        $returnTo = self::tenantAdminReturnTo($host);

        return 'https://artsfol.io/auth/' . rawurlencode($provider)
            . '?return_to=' . rawurlencode($returnTo);
    }

    private static function tenantAdminReturnTo(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'artsfol.io') {
            return '/platform/admin';
        }

        return 'https://' . $host . '/admin';
    }

}

// End of file.

