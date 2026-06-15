<?php
/**
 * Cloudflare Turnstile helper for public ArtsFolio forms.
 */

declare(strict_types=1);

namespace App\Services;

/**
 * Renders and verifies Cloudflare Turnstile challenges.
 *
 * The historical class name is kept so existing controllers can continue to
 * call one form-protection service while the implementation moves from the old
 * first-party checkbox/reCAPTCHA era to Turnstile.
 */
final class FirstPartyCaptcha
{
    private const VERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Renders a Turnstile widget for a public form when a site key exists.
     *
     * @param string|null $siteKey Site-specific key, usually from settings.
     */
    public static function render(string $purpose, int $tenantId, ?string $siteKey = null): string
    {
        $siteKey = self::configuredSiteKey($siteKey);
        if ($siteKey === '') {
            return '';
        }

        $escapedSiteKey = self::escape($siteKey);
        $escapedPurpose = self::escape($purpose);
        $escapedTenantId = self::escape((string) $tenantId);

        return <<<HTML
<div class="cf-turnstile" data-sitekey="{$escapedSiteKey}" data-action="{$escapedPurpose}" data-cdata="tenant-{$escapedTenantId}"></div>
HTML;
    }

    /**
     * Verifies a submitted Turnstile token with Cloudflare Siteverify.
     *
     * A blank secret key intentionally allows local/staging forms through, which
     * preserves the existing development behavior. Production should configure
     * ARTSFOLIO_TURNSTILE_SECRET_KEY or platform/tenant settings.
     */
    public static function verify(
        string $purpose,
        int $tenantId,
        array $post,
        ?string $secretKey = null,
        ?string $remoteIp = null,
    ): CaptchaResult {
        $secretKey = self::configuredSecretKey($secretKey);
        if ($secretKey === '') {
            return CaptchaResult::pass();
        }

        $responseToken = trim((string) ($post['cf-turnstile-response'] ?? ''));
        if ($responseToken === '') {
            return CaptchaResult::fail('Please complete the Turnstile verification.');
        }

        $payload = [
            'secret' => $secretKey,
            'response' => $responseToken,
        ];

        if ($remoteIp !== null && trim($remoteIp) !== '') {
            $payload['remoteip'] = trim($remoteIp);
        }

        $raw = self::postToSiteverify($payload);
        if ($raw === null) {
            return CaptchaResult::fail('Turnstile verification could not be reached. Please try again.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return CaptchaResult::fail('Turnstile verification failed. Please try again.');
        }

        return CaptchaResult::pass();
    }

    /**
     * Returns true when a site key is available from settings or environment.
     */
    public static function isConfigured(?string $siteKey = null): bool
    {
        return self::configuredSiteKey($siteKey) !== '';
    }

    /**
     * Posts the Siteverify request with a short timeout for public form UX.
     *
     * @param array<string,string> $payload
     */
    private static function postToSiteverify(array $payload): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'timeout' => 4,
            ],
        ]);

        $raw = @file_get_contents(self::VERIFY_ENDPOINT, false, $context);

        return $raw === false ? null : $raw;
    }

    private static function configuredSiteKey(?string $siteKey): string
    {
        $siteKey = trim((string) $siteKey);
        if ($siteKey !== '') {
            return $siteKey;
        }

        return trim((string) (getenv('ARTSFOLIO_TURNSTILE_SITE_KEY') ?: getenv('TURNSTILE_SITE_KEY') ?: ''));
    }

    private static function configuredSecretKey(?string $secretKey): string
    {
        $secretKey = trim((string) $secretKey);
        if ($secretKey !== '') {
            return $secretKey;
        }

        return trim((string) (getenv('ARTSFOLIO_TURNSTILE_SECRET_KEY') ?: getenv('TURNSTILE_SECRET_KEY') ?: ''));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Small value object for Turnstile verification results.
 */
final class CaptchaResult
{
    private function __construct(
        public readonly bool $passed,
        public readonly string $message = '',
    ) {
    }

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}

// End of file.
