<?php

/**
 * Tenant public email signup controller.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Security\RateLimiter;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Analytics\AnalyticsLocationResolver;
use App\Services\FirstPartyCaptcha;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Signup\EmailSignupService;
use PDO;
use Throwable;

/**
 * Handles tenant public email signup submissions.
 */
final class SignupController
{
    public function __construct(
        private readonly EmailSignupService $signups,
        private readonly CsrfTokenService $csrf,
        private readonly ?RateLimiter $rateLimiter = null,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function submit(Request $request, TenantContext $tenant): Response
    {
        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? '/contact'));

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return $this->signupResult($request, $returnTo, false, 'The signup security check expired. Please try again.', 'signup_error=security', 419);
        }

        $captcha = FirstPartyCaptcha::verify('signup', (int) $tenant->tenantId, $_POST);
        if (!$captcha->passed) {
            return $this->signupResult($request, $returnTo, false, 'Please confirm that you are human and try again.', 'signup_error=recaptcha', 422);
        }

        if ($this->rateLimiter && !$this->rateLimiter->allow($this->rateKey($request, $tenant), 5, 300)) {
            return $this->signupResult($request, $returnTo, false, 'Too many signup attempts were received. Please wait a few minutes and try again.', 'signup_error=rate_limited', 429);
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $source = trim((string) ($_POST['source'] ?? 'public_site')) ?: 'public_site';

        if ($email === '') {
            return $this->signupResult($request, $returnTo, false, 'Please enter an email address.', 'signup_error=missing', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->signupResult($request, $returnTo, false, 'Please enter a valid email address.', 'signup_error=email', 422);
        }

        $ipAddress = $request->server('REMOTE_ADDR');
        $location = $this->resolveLocation($request, $ipAddress);

        $this->signups->receive(
            tenant: $tenant,
            email: $email,
            name: $name !== '' ? $name : null,
            source: $source,
            ipAddress: $ipAddress,
            userAgent: $request->server('HTTP_USER_AGENT'),
            country: $location['country'],
            region: $location['region'],
            city: $location['city'],
        );

        return $this->signupResult($request, $returnTo, true, 'Thank you. You have been added to the email list.', 'signup_sent=1');
    }

    /**
     * Resolves location for engagement records using the same coarse resolver as analytics.
     *
     * @return array{country:string,region:string,city:string,source:string}
     */
    private function resolveLocation(Request $request, string $ipAddress): array
    {
        if ($this->pdo === null || $ipAddress === '') {
            return ['country' => '', 'region' => '', 'city' => '', 'source' => 'unavailable'];
        }

        try {
            $ipHash = hash('sha256', $ipAddress . '|artsfolio-analytics');

            return (new AnalyticsLocationResolver($this->pdo))->resolve($request, $ipAddress, $ipHash);
        } catch (Throwable) {
            return ['country' => '', 'region' => '', 'city' => '', 'source' => 'error'];
        }
    }

    /**
     * Returns JSON to progressive-enhanced forms and redirects ordinary posts.
     * This keeps the footer/contact signup result area from displaying the
     * complete redirected page as an error blob.
     */
    private function signupResult(Request $request, string $returnTo, bool $ok, string $message, string $query, int $status = 200): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json([
                'ok' => $ok,
                'message' => $message,
            ], $status);
        }

        return $this->backTo($returnTo, $query);
    }

    /**
     * Detects JavaScript form submissions while preserving normal HTML fallback.
     */
    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->server('HTTP_ACCEPT', ''));
        $requestedWith = strtolower((string) $request->server('HTTP_X_REQUESTED_WITH', ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private function backTo(string $returnTo, string $query): Response
    {
        $separator = str_contains($returnTo, '?') ? '&' : '?';

        return new Response('', 303, ['Location' => $returnTo . $separator . $query]);
    }

    private function safeReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return '/contact';
        }

        return $returnTo;
    }

    private function rateKey(Request $request, TenantContext $tenant): string
    {
        $ip = $request->server('REMOTE_ADDR', 'unknown');

        return 'signup:' . $tenant->tenantId . ':' . $ip;
    }
}

// End of file.
