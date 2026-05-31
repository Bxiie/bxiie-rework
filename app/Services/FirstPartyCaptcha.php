<?php
/**
 * First-party checkbox CAPTCHA for tenant public forms.
 */

declare(strict_types=1);

namespace App\Services;

/**
 * Issues and verifies signed, session-bound checkbox challenges.
 *
 * This intentionally avoids third-party domain-bound CAPTCHA providers on
 * tenant public sites. It is not meant to defeat targeted attackers by itself;
 * it provides friction for commodity bots and is paired with honeypot fields,
 * dwell-time checks, one-time use, expiry, and existing rate limits.
 */
final class FirstPartyCaptcha
{
    private const SESSION_KEY = 'artsfolio_first_party_captcha';
    private const TOKEN_TTL_SECONDS = 900;
    private const MIN_DWELL_SECONDS = 2;

    /**
     * Renders the checkbox challenge markup for a public form.
     */
    public static function render(string $purpose, int $tenantId): string
    {
        $challenge = self::issue($purpose, $tenantId);
        $token = self::escape($challenge['token']);
        $issuedAt = self::escape((string) $challenge['issued_at']);

        return <<<HTML
<div class="af-captcha" data-af-captcha>
    <input type="hidden" name="af_captcha_token" value="{$token}">
    <input type="hidden" name="af_captcha_issued_at" value="{$issuedAt}">
    <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" class="af-honeypot" aria-hidden="true">
    <label class="af-captcha-box">
        <input type="checkbox" name="af_captcha_confirm" value="1" required disabled>
        <span>I’m human</span>
    </label>
    <small class="af-captcha-help">The checkbox unlocks after a moment. This keeps spam bots out without Google reCAPTCHA.</small>
</div>
HTML;
    }

    /**
     * Verifies a public form CAPTCHA submission.
     */
    public static function verify(string $purpose, int $tenantId, array $post): CaptchaResult
    {
        self::ensureSession();

        if (trim((string) ($post['website_url'] ?? '')) !== '') {
            return CaptchaResult::fail('Please try again.');
        }

        if ((string) ($post['af_captcha_confirm'] ?? '') !== '1') {
            return CaptchaResult::fail('Please check the human confirmation box.');
        }

        $token = trim((string) ($post['af_captcha_token'] ?? ''));
        $issuedAt = (int) ($post['af_captcha_issued_at'] ?? 0);
        if ($token === '' || $issuedAt <= 0) {
            return CaptchaResult::fail('The human confirmation expired. Please reload the form and try again.');
        }

        $payload = self::decodeToken($token);
        if ($payload === null) {
            return CaptchaResult::fail('The human confirmation could not be verified. Please reload the form and try again.');
        }

        if (($payload['purpose'] ?? '') !== $purpose || (int) ($payload['tenant_id'] ?? 0) !== $tenantId) {
            return CaptchaResult::fail('The human confirmation does not match this form. Please reload and try again.');
        }

        if ((int) ($payload['issued_at'] ?? 0) !== $issuedAt) {
            return CaptchaResult::fail('The human confirmation timestamp is invalid. Please reload and try again.');
        }

        $age = time() - $issuedAt;
        if ($age < self::MIN_DWELL_SECONDS) {
            return CaptchaResult::fail('Please wait a moment before submitting the form.');
        }

        if ($age > self::TOKEN_TTL_SECONDS) {
            self::consumeNonce((string) ($payload['nonce'] ?? ''));
            return CaptchaResult::fail('The human confirmation expired. Please reload the form and try again.');
        }

        $nonce = (string) ($payload['nonce'] ?? '');
        $stored = $_SESSION[self::SESSION_KEY][$nonce] ?? null;
        if (!is_array($stored) || !empty($stored['consumed'])) {
            return CaptchaResult::fail('The human confirmation was already used. Please reload the form and try again.');
        }

        if (($stored['purpose'] ?? '') !== $purpose || (int) ($stored['tenant_id'] ?? 0) !== $tenantId) {
            return CaptchaResult::fail('The human confirmation does not match this form. Please reload and try again.');
        }

        self::consumeNonce($nonce);

        return CaptchaResult::pass();
    }

    /**
     * Creates and stores a one-time challenge bound to this PHP session.
     *
     * @return array{token:string,issued_at:int}
     */
    private static function issue(string $purpose, int $tenantId): array
    {
        self::ensureSession();
        self::garbageCollect();

        $issuedAt = time();
        $nonce = bin2hex(random_bytes(16));
        $payload = [
            'purpose' => $purpose,
            'tenant_id' => $tenantId,
            'issued_at' => $issuedAt,
            'nonce' => $nonce,
        ];

        $_SESSION[self::SESSION_KEY][$nonce] = [
            'purpose' => $purpose,
            'tenant_id' => $tenantId,
            'issued_at' => $issuedAt,
            'consumed' => false,
        ];

        return [
            'token' => self::encodeToken($payload),
            'issued_at' => $issuedAt,
        ];
    }

    /**
     * Encodes and signs the challenge payload.
     *
     * @param array<string,int|string> $payload
     */
    private static function encodeToken(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $body, self::secret());

        return $body . '.' . $signature;
    }

    /**
     * Decodes and verifies a signed token.
     *
     * @return array<string,mixed>|null
     */
    private static function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, self::secret());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Marks a challenge nonce consumed so replays fail.
     */
    private static function consumeNonce(string $nonce): void
    {
        if ($nonce !== '' && isset($_SESSION[self::SESSION_KEY][$nonce])) {
            $_SESSION[self::SESSION_KEY][$nonce]['consumed'] = true;
        }
    }

    /**
     * Keeps session challenge storage bounded.
     */
    private static function garbageCollect(): void
    {
        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] ?? [] as $nonce => $challenge) {
            $issuedAt = (int) ($challenge['issued_at'] ?? 0);
            if ($issuedAt <= 0 || ($now - $issuedAt) > self::TOKEN_TTL_SECONDS) {
                unset($_SESSION[self::SESSION_KEY][$nonce]);
            }
        }
    }

    /**
     * Returns a stable per-install signing secret without requiring new config.
     */
    private static function secret(): string
    {
        $configured = (string) (getenv('APP_KEY') ?: getenv('ARTSFOLIO_APP_KEY') ?: getenv('ARTSFOLIO_CAPTCHA_SECRET') ?: '');
        if ($configured !== '') {
            return $configured;
        }

        return session_id() . '|artsfolio-first-party-captcha';
    }

    /**
     * Makes sure CAPTCHA state can be kept server-side.
     */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Small value object for first-party CAPTCHA verification results.
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
