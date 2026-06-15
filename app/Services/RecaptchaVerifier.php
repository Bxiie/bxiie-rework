<?php
/**
 * Backward-compatible wrapper for Cloudflare Turnstile verification.
 */

declare(strict_types=1);

namespace App\Services;

/**
 * @deprecated Use FirstPartyCaptcha::verify() with Turnstile settings instead.
 */
final class RecaptchaVerifier
{
    public static function verify(?string $secretKey, ?string $responseToken, ?string $remoteIp): bool
    {
        $result = FirstPartyCaptcha::verify(
            'legacy_public_form',
            0,
            ['cf-turnstile-response' => (string) $responseToken],
            $secretKey,
            $remoteIp,
        );

        return $result->passed;
    }
}

// End of file.
