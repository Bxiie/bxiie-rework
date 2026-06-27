<?php
/**
 * Public form spam-protection helper.
 *
 * Platform forms continue to use Cloudflare Turnstile when a platform site key
 * and secret key are configured. Tenant public forms intentionally fall back to
 * the built-in ArtsFolio CAPTCHA so tenant/custom domains do not depend on
 * Cloudflare widget hostname registration.
 */

declare(strict_types=1);

namespace App\Services;

/**
 * Renders and verifies public form challenges.
 */
final class FirstPartyCaptcha
{
    private const VERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const SESSION_KEY = 'artsfolio_first_party_captcha';
    private const MIN_DWELL_SECONDS = 2;
    private const MAX_AGE_SECONDS = 1200;

    /**
     * Renders Turnstile when a site key is supplied, otherwise renders the
     * built-in ArtsFolio challenge.
     *
     * @param string|null $siteKey Platform Turnstile site key. Tenant callers
     *                             should pass null/blank to use first-party mode.
     */
    public static function render(string $purpose, int $tenantId, ?string $siteKey = null): string
    {
        $siteKey = self::configuredSiteKey($siteKey);
        if ($siteKey !== '') {
            $escapedSiteKey = self::escape($siteKey);
            $escapedPurpose = self::escape($purpose);
            $escapedTenantId = self::escape((string) $tenantId);

            return <<<HTML
<div class="cf-turnstile" data-sitekey="{$escapedSiteKey}" data-action="{$escapedPurpose}" data-cdata="tenant-{$escapedTenantId}"></div>
HTML;
        }

        return self::renderFirstParty($purpose, $tenantId);
    }

    /**
     * Verifies Turnstile when a secret key is supplied, otherwise verifies the
     * built-in session-backed challenge.
     */
    public static function verify(
        string $purpose,
        int $tenantId,
        array $post,
        ?string $secretKey = null,
        ?string $remoteIp = null,
    ): CaptchaResult {
        $secretKey = self::configuredSecretKey($secretKey);
        if ($secretKey !== '') {
            return self::verifyTurnstile($purpose, $tenantId, $post, $secretKey, $remoteIp);
        }

        return self::verifyFirstParty($purpose, $tenantId, $post);
    }

    /**
     * Returns true when a Turnstile site key is available.
     */
    public static function isConfigured(?string $siteKey = null): bool
    {
        return self::configuredSiteKey($siteKey) !== '';
    }

    private static function renderFirstParty(string $purpose, int $tenantId): string
    {
        self::ensureSession();
        self::pruneExpiredChallenges();

        $token = bin2hex(random_bytes(24));
        $_SESSION[self::SESSION_KEY][$token] = [
            'purpose' => $purpose,
            'tenant_id' => $tenantId,
            'not_before' => time() + self::MIN_DWELL_SECONDS,
            'expires_at' => time() + self::MAX_AGE_SECONDS,
        ];

        $escapedToken = self::escape($token);

        return <<<HTML
<div class="af-captcha" data-af-captcha>
    <input type="hidden" name="af_captcha_token" value="{$escapedToken}">
    <label class="af-captcha-field" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
        Website
        <input type="text" name="website_url" value="" autocomplete="off" tabindex="-1">
    </label>
    <label class="af-captcha-confirm">
        <input type="checkbox" name="af_captcha_confirm" value="1" required>
        I am a real person.
    </label>
</div>
HTML;
    }

    private static function verifyFirstParty(string $purpose, int $tenantId, array $post): CaptchaResult
    {
        self::ensureSession();
        self::pruneExpiredChallenges();

        if (trim((string) ($post['website_url'] ?? '')) !== '') {
            return CaptchaResult::fail('Spam check failed.');
        }

        if ((string) ($post['af_captcha_confirm'] ?? '') !== '1') {
            return CaptchaResult::fail('Please confirm you are a real person.');
        }

        $token = trim((string) ($post['af_captcha_token'] ?? ''));
        if ($token === '') {
            return CaptchaResult::fail('Spam check is missing. Please reload the page and try again.');
        }

        $challenge = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (!is_array($challenge)) {
            return CaptchaResult::fail('Spam check expired. Please reload the page and try again.');
        }

        if (($challenge['purpose'] ?? '') !== $purpose || (int) ($challenge['tenant_id'] ?? -1) !== $tenantId) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            return CaptchaResult::fail('Spam check did not match this form. Please reload the page and try again.');
        }

        $now = time();
        if ($now > (int) ($challenge['expires_at'] ?? 0)) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            return CaptchaResult::fail('Spam check expired. Please reload the page and try again.');
        }

        if ($now < (int) ($challenge['not_before'] ?? 0)) {
            return CaptchaResult::fail('Please wait a moment before submitting the form.');
        }

        unset($_SESSION[self::SESSION_KEY][$token]);

        return CaptchaResult::pass();
    }

    private static function verifyTurnstile(
        string $purpose,
        int $tenantId,
        array $post,
        string $secretKey,
        ?string $remoteIp,
    ): CaptchaResult {
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

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    private static function pruneExpiredChallenges(): void
    {
        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $token => $challenge) {
            if (!is_array($challenge) || $now > (int) ($challenge['expires_at'] ?? 0)) {
                unset($_SESSION[self::SESSION_KEY][$token]);
            }
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Small value object for public form verification results.
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
