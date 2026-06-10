<?php

/**
 * Tenant public contact submission controller.
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
use App\Tenant\Contact\ContactMessageService;
use PDO;
use Throwable;

/**
 * Handles tenant public contact message submissions.
 */
final class ContactController
{
    public function __construct(
        private readonly ContactMessageService $messages,
        private readonly CsrfTokenService $csrf,
        private readonly ?RateLimiter $rateLimiter = null,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function submit(Request $request, TenantContext $tenant): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return $this->contactResult($request, false, 'The form security check expired. Please try again.', 'contact_error=security', 419);
        }

        $captcha = FirstPartyCaptcha::verify('contact', (int) $tenant->tenantId, $_POST);
        if (!$captcha->passed) {
            return $this->contactResult($request, false, 'Please confirm that you are human and try again.', 'contact_error=recaptcha', 422);
        }

        if ($this->rateLimiter && !$this->rateLimiter->allow($this->rateKey($request, $tenant), 5, 300)) {
            return $this->contactResult($request, false, 'Too many submissions were received. Please wait a few minutes and try again.', 'contact_error=rate_limited', 429);
        }

        $senderName = trim((string) ($_POST['name'] ?? ''));
        $senderEmail = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($senderName === '' || $senderEmail === '' || $message === '') {
            return $this->contactResult($request, false, 'Please complete the required fields.', 'contact_error=missing', 422);
        }

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->contactResult($request, false, 'Please enter a valid email address.', 'contact_error=email', 422);
        }

        $ipAddress = $request->server('REMOTE_ADDR');
        $location = $this->resolveLocation($request, $ipAddress);

        $this->messages->receive(
            tenant: $tenant,
            senderName: $senderName,
            senderEmail: $senderEmail,
            message: $message,
            subject: $subject !== '' ? $subject : null,
            ipAddress: $ipAddress,
            userAgent: $request->server('HTTP_USER_AGENT'),
            country: $location['country'],
            region: $location['region'],
            city: $location['city'],
        );

        return $this->contactResult($request, true, 'Thank you. Your message has been sent.', 'contact_sent=1');
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
     * This prevents fetch() from following the 303 back to /contact and showing
     * the whole rendered page inside the red form message area.
     */
    private function contactResult(Request $request, bool $ok, string $message, string $query, int $status = 200): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json([
                'ok' => $ok,
                'message' => $message,
            ], $status);
        }

        return $this->backToContact($query);
    }

    /**
     * Detects the tenant JavaScript form submission without making browser-only
     * behavior mandatory for normal HTML forms.
     */
    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->server('HTTP_ACCEPT', ''));
        $requestedWith = strtolower((string) $request->server('HTTP_X_REQUESTED_WITH', ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private function backToContact(string $query): Response
    {
        return new Response('', 303, ['Location' => '/contact?' . $query]);
    }

    private function rateKey(Request $request, TenantContext $tenant): string
    {
        $ip = $request->server('REMOTE_ADDR', 'unknown');

        return 'contact:' . $tenant->tenantId . ':' . $ip;
    }
}

// End of file.
