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
            return $this->backTo($returnTo, 'signup_error=security');
        }

        $captcha = FirstPartyCaptcha::verify('signup', (int) $tenant->tenantId, $_POST, $this->turnstileSecretKey($tenant), $request->server('REMOTE_ADDR'));
        if (!$captcha->passed) {
            return $this->backTo($returnTo, 'signup_error=recaptcha');
        }

        if ($this->rateLimiter && !$this->rateLimiter->allow($this->rateKey($request, $tenant), 5, 300)) {
            return $this->backTo($returnTo, 'signup_error=rate_limited');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $source = trim((string) ($_POST['source'] ?? 'public_site')) ?: 'public_site';

        if ($email === '') {
            return $this->backTo($returnTo, 'signup_error=missing');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->backTo($returnTo, 'signup_error=email');
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

        return $this->backTo($returnTo, 'signup_sent=1');
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
     * Tenant public forms use the built-in ArtsFolio CAPTCHA, not Turnstile.
     */
    private function turnstileSecretKey(TenantContext $tenant): string
    {
        return '';
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
