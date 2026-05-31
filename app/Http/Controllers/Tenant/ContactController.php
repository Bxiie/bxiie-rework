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
use App\Services\RecaptchaVerifier;
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

        if (!RecaptchaVerifier::verify($this->recaptchaSecret($tenant), $_POST['g-recaptcha-response'] ?? null, $request->server('REMOTE_ADDR'))) {
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

        $this->messages->receive(
            tenant: $tenant,
            senderName: $senderName,
            senderEmail: $senderEmail,
            message: $message,
            subject: $subject !== '' ? $subject : null,
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT'),
        );

        return $this->backToContact('contact_sent=1');
    }

    /**
     * Tenant contact forms use only tenant-specific reCAPTCHA secrets.
     * Platform keys are not reused on tenant/custom domains because Google
     * validates each site key against allowed hostnames.
     */
    private function recaptchaSecret(TenantContext $tenant): string
    {
        if ($this->pdo === null) {
            return '';
        }

        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM tenant_settings WHERE tenant_id = :tenant_id AND setting_key = 'recaptcha_secret_key' LIMIT 1");
            $stmt->execute(['tenant_id' => $tenant->tenantId]);

            return trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (Throwable) {
            return '';
        }
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
