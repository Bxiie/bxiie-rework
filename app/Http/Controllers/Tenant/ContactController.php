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
            return $this->backToContact('contact_error=security');
        }

        $captcha = FirstPartyCaptcha::verify('contact', (int) $tenant->tenantId, $_POST, $this->turnstileSecretKey($tenant), $request->server('REMOTE_ADDR'));
        if (!$captcha->passed) {
            return $this->backToContact('contact_error=recaptcha');
        }

        if ($this->rateLimiter && !$this->rateLimiter->allow($this->rateKey($request, $tenant), 5, 300)) {
            return $this->backToContact('contact_error=rate_limited');
        }

        $senderName = trim((string) ($_POST['name'] ?? ''));
        $senderEmail = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($senderName === '' || $senderEmail === '' || $message === '') {
            return $this->backToContact('contact_error=missing');
        }

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->backToContact('contact_error=email');
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

        return $this->backToContact('contact_sent=1');
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
     * Returns tenant Turnstile secret key, falling back to platform/global env.
     */
    private function turnstileSecretKey(TenantContext $tenant): string
    {
        if (!$this->pdo) {
            return trim((string) (getenv('ARTSFOLIO_TURNSTILE_SECRET_KEY') ?: ''));
        }

        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM tenant_settings WHERE tenant_id = :tenant_id AND setting_key = 'turnstile_secret_key' LIMIT 1");
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $tenantValue = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($tenantValue !== '') {
                return $tenantValue;
            }

            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'turnstile_secret_key' LIMIT 1");
            $stmt->execute();
            $platformValue = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($platformValue !== '') {
                return $platformValue;
            }
        } catch (Throwable) {
            // Do not hard-fail public form submissions because settings lookup failed.
        }

        return trim((string) (getenv('ARTSFOLIO_TURNSTILE_SECRET_KEY') ?: ''));
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
