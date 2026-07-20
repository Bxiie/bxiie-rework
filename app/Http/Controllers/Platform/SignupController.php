<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Platform\Auth\SignupPostRegistrationMailer;

use App\Http\Request;
use App\Http\Response;
use App\Http\Support\SessionCookie;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Http\Controllers\Auth\LoginController;
use App\Platform\Signup\TenantSignupService;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Platform\Billing\StripeSubscriptionCheckoutService;
use App\Support\Security\CsrfTokenService;

/**
 * Handles public platform tenant signup.
 */
final class SignupController
{
    public function __construct(
        private readonly TenantSignupService $signups,
        private readonly PasswordHasher $passwords,
        private readonly CsrfTokenService $csrf,
        private readonly ?SessionRepository $sessions = null,
        private readonly ?SessionTokenService $sessionTokens = null,
        private readonly ?PlatformSettingsRepository $settings = null,
        private readonly ?SignupPostRegistrationMailer $postRegistrationMailer = null,) {
    }

    public function show(Request $request): Response
    {
        // ARTSFOLIO_SIGNUP_PASSCODE_PREPROMPT
        $signupEntryCode = trim((string) ($_GET['code'] ?? ''));
        $signupCodeData = null;
        if ($this->signups->requiresSignupCode() || $signupEntryCode !== '') {
            try {
                $signupCodeData = $this->signups->validateSignupEntryCode($signupEntryCode);
            } catch (\Throwable $e) {
                return $this->signupCodePrompt($signupEntryCode === '' ? '' : $e->getMessage());
            }
        }

        $csrfToken = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $signupCodeRaw = (string) ($_GET['code'] ?? '');
        $signupCode = htmlspecialchars($signupCodeRaw, ENT_QUOTES, 'UTF-8');
        $oauthProfile = is_array($_SESSION['artsfolio_oauth_profile'] ?? null) ? $_SESSION['artsfolio_oauth_profile'] : null;
        $oauthName = htmlspecialchars((string) ($oauthProfile['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $oauthEmailRaw = strtolower(trim((string) ($oauthProfile['email'] ?? '')));
        $oauthEmail = htmlspecialchars($oauthEmailRaw, ENT_QUOTES, 'UTF-8');
        $oauthProvider = htmlspecialchars(ucfirst((string) ($oauthProfile['provider'] ?? 'SSO')), ENT_QUOTES, 'UTF-8');
        $emailField = $oauthEmailRaw !== ''
            ? '<label>Email<input type="email" name="email" autocomplete="email" value="' . $oauthEmail . '" readonly aria-readonly="true" class="readonly-input" required></label><p class="auth-notice">This email came from ' . $oauthProvider . ' and cannot be changed during OAuth signup.</p>'
            : '<label>Email<input type="email" name="email" autocomplete="email" required></label>';
        $passwordBlock = $oauthEmailRaw !== ''
            ? '<p class="auth-notice">Signed in with ' . $oauthProvider . ' as ' . $oauthEmail . '. Choose a site name and slug to create your tenant.</p>'
            : '<label>Password<input type="password" name="password" autocomplete="new-password" minlength="10" required></label>';
        $oauthReturnTo = htmlspecialchars($this->signupReturnTo($signupCodeRaw), ENT_QUOTES, 'UTF-8');
        $googleSignupUrl = '/auth/google?return_to=' . rawurlencode($oauthReturnTo);
        $facebookSignupUrl = '/auth/facebook?return_to=' . rawurlencode($oauthReturnTo);
        $planBlock = $this->signupPlanBlock($signupCodeData, (string) ($_GET['plan'] ?? 'free'));

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create an ArtsFolio site | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/auth.css?v=20260708-logo-aspect">
</head>
<body>
<main class="auth-page">
    <section class="auth-card">
        <a href="/" class="auth-logo-link" aria-label="ArtsFolio home">
            <img src="/assets/logo_2.png" alt="ArtsFolio" class="auth-logo">
        </a>
        <p class="auth-eyebrow">Start your site</p>
        <h1>Create an ArtsFolio site</h1>
        <p class="auth-copy">Create the tenant, public subdomain, first owner account, membership, provisioning jobs, and welcome email queue in one flow.</p>
        <div class="sso-row">
            <a href="{$googleSignupUrl}"><span class="oauth-brand oauth-google" aria-hidden="true">G</span> Continue with Google</a>
            <a href="{$facebookSignupUrl}"><span class="oauth-brand oauth-facebook" aria-hidden="true">f</span> Continue with Facebook</a>
        </div>
        <div class="divider"><span>or continue below</span></div>
        <form method="post" action="/signup" class="auth-form">
            <input type="hidden" name="csrf_token" value="{$csrfToken}">
            <label>Site name<input type="text" name="site_name" required></label>
            <label>Site short name<input type="text" name="slug" pattern="[a-z0-9-]{3,63}" required><small class="form-help">Choose the short name for your ArtsFolio address. For example, “bxiie” creates bxiie.artsfol.io.</small></label>
            <label>Your name<input type="text" name="admin_name" autocomplete="name" value="{$oauthName}"></label>
            {$emailField}
            <label>Signup passcode<input type="text" name="signup_code" value="{$signupCode}" autocomplete="off"></label>
            {$planBlock}
            {$passwordBlock}
            <button type="submit">Create site</button>
        </form>
        <p class="auth-links"><a href="/login">Already have an account?</a><a href="/help">Need help?</a></p>
    </section>
</main>
</body>
</html>
HTML);
    }

    public function submit(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
        }

        $password = (string) ($_POST['password'] ?? '');
        $oauthProfile = is_array($_SESSION['artsfolio_oauth_profile'] ?? null) ? $_SESSION['artsfolio_oauth_profile'] : null;
        $oauthUserId = isset($_SESSION['artsfolio_oauth_user_id']) ? max(0, (int) $_SESSION['artsfolio_oauth_user_id']) : null;
        $oauthEmail = strtolower(trim((string) ($oauthProfile['email'] ?? '')));
        $adminEmail = $oauthProfile !== null ? $oauthEmail : (string) ($_POST['email'] ?? '');

        if ($oauthProfile !== null && ($oauthEmail === '' || !filter_var($oauthEmail, FILTER_VALIDATE_EMAIL))) {
            return Response::error(422, 'The OAuth provider did not provide a valid email address. Please try another sign-in method.');
        }

        if ($oauthProfile === null && strlen($password) < 10) {
            return Response::error(422, 'Use at least 10 characters for your password.');
        }

        if ($oauthProfile !== null && $password === '') {
            $password = bin2hex(random_bytes(24));
        }

        try {
            $result = $this->signups->register(
                slug: (string) ($_POST['slug'] ?? ''),
                siteName: (string) ($_POST['site_name'] ?? ''),
                adminEmail: $adminEmail,
                adminName: (string) ($_POST['admin_name'] ?? ''),
                passwordHash: $this->passwords->hash($password),
                signupCode: (string) ($_POST['signup_code'] ?? ''),
                existingUserId: $oauthUserId,
                createPasswordIdentity: $oauthProfile === null,
                selectedPlanSlug: (string) ($_POST['selected_plan'] ?? ''),
            );

            
            if ($this->postRegistrationMailer !== null) {
                try {
                    $this->postRegistrationMailer->queueForEmail(
                        (string) ($_POST['admin_email'] ?? $_POST['email'] ?? ''),
                        (string) ($result['tenant_slug'] ?? $_POST['slug'] ?? $_POST['site_slug'] ?? ''),
                    );
                } catch (\Throwable $exception) {
                    error_log('ArtsFolio signup post-registration email queue failed: ' . $exception->getMessage());
                }
            }
unset($_SESSION['artsfolio_oauth_profile'], $_SESSION['artsfolio_oauth_user_id']);
        } catch (\Throwable $e) {
            error_log('Tenant signup failed: ' . $e->getMessage());

            return Response::error(
                422,
                'Could not create site: ' . $e->getMessage(),
            );
        }

        $sessionToken = $this->createBrowserSession((int) $result['user_id'], (int) $result['tenant_id']);

        $headers = ['Location' => $this->gettingStartedUrl((string) $result['domain'])];

        if ($sessionToken !== '') {
            $headers['Set-Cookie'] = SessionCookie::issueSetCookie($sessionToken, true);
        }

        if (($result['requires_immediate_checkout'] ?? false) === true && $this->settings !== null) {
            try {
                $checkout = (new StripeSubscriptionCheckoutService())->createSubscriptionSession((string) $this->settings->get('stripe_secret_key', ''), (int) $result['tenant_id'], (array) ($result['selected_plan'] ?? []), 'https://' . (string) $result['domain'] . '/admin/billing?notice=billing-complete', 'https://' . (string) $result['domain'] . '/admin/billing?notice=billing-canceled', strtolower(trim($adminEmail)), 0);
                $headers['Location'] = (string) $checkout['url'];
            } catch (\Throwable $e) {
                error_log('Tenant paid signup checkout failed: ' . $e->getMessage());
            }
        }

        return new Response('', 302, $headers);
    }


    /**
     * Builds the return path used when OAuth is launched from tenant signup.
     */
    private function signupReturnTo(string $signupCode): string
    {
        $signupCode = trim($signupCode);
        if ($signupCode === '') {
            return '/signup';
        }

        return '/signup?code=' . rawurlencode($signupCode);
    }

    /**
     * Renders the public signup plan selector.
     */
    private function signupPlanBlock(?array $signupCode, string $selectedPlan): string
    {
        $plans = $this->signups->activePlans();
        if ($plans === []) { return ''; }
        $selectedPlan = strtolower(trim($selectedPlan)) ?: 'free';
        $months = $signupCode !== null ? max(0, (int) ($signupCode['free_access_months'] ?? 0)) : 0;
        $options = '';
        foreach ($plans as $plan) {
            $slugRaw = (string) $plan['slug'];
            $slug = htmlspecialchars($slugRaw, ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars((string) $plan['name'], ENT_QUOTES, 'UTF-8');
            $priceCents = (int) ($plan['monthly_price_cents'] ?? 0);
            $price = $priceCents > 0 ? '$' . number_format($priceCents / 100, 2) . '/month' : 'Free';
            $selected = strtolower($slugRaw) === $selectedPlan ? ' selected' : '';
            $options .= '<option value="' . $slug . '"' . $selected . '>' . $name . ' · ' . htmlspecialchars($price, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $note = $months > 0 ? '<p class="auth-notice">This signup code grants free access to the selected plan for ' . htmlspecialchars($months . ' month' . ($months === 1 ? '' : 's'), ENT_QUOTES, 'UTF-8') . '. After that, paid plans require card details and bill monthly.</p>' : '<p class="auth-notice">Paid plans require card details and are billed immediately, then monthly. Free can be used without a card.</p>';
        return '<label>Choose your plan<select name="selected_plan" required>' . $options . '</select></label>' . $note;
    }

    /**
     * Renders the signup-code gate before collecting tenant details.
     */
    private function signupCodePrompt(string $error = ''): Response
    {
        $errorHtml = $error !== ''
            ? '<p class="auth-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>'
            : '';

        return Response::html(<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Signup passcode required | ArtsFolio</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/auth.css?v=20260708-logo-aspect"></head><body><main class="auth-page"><section class="auth-card"><a href="/" class="auth-logo-link"><img src="/assets/logo_2.png" alt="ArtsFolio" class="auth-logo"></a><p class="auth-eyebrow">Private signup</p><h1>Enter your signup passcode</h1><p class="auth-copy">A passcode is required before new site details can be entered.</p>{$errorHtml}<form method="get" action="/signup" class="auth-form"><label>Signup passcode<input type="text" name="code" autocomplete="off" required autofocus></label><button type="submit">Continue</button></form></section></main></body></html>
HTML);
    }

    private function createBrowserSession(int $userId, ?int $tenantId = null): string
    {
        if ($this->sessions === null || $this->sessionTokens === null) {
            return '';
        }

        $token = $this->sessionTokens->generateToken();
        $hash = $this->sessionTokens->hashToken($token);

        $this->sessions->create(
            sessionHash: $hash,
            userId: $userId,
            tenantId: $tenantId,
            ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
        );

        return $token;
    }

    private function isSecureCookie(): bool
    {
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));

        if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Builds the post-signup login URL.
     *
     * Production uses HTTPS without a port. Local development may use the PHP
     * built-in server, so tests and browser smoke checks can set:
     *
     *   APP_ENV=local
     *   ARTSFOLIO_LOCAL_DEV_PORT=8080
     */
    private function gettingStartedUrl(string $domain): string
    {
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));
        $localPort = trim((string) (getenv('ARTSFOLIO_LOCAL_DEV_PORT') ?: ''));

        if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
            $port = $localPort !== '' ? ':' . $localPort : '';

            return 'http://' . $domain . $port . '/admin/getting-started';
        }

        return 'https://' . $domain . '/admin/getting-started';
    }
}

// End of file.
