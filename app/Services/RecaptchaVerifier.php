<?php
/**
 * Server-side Google reCAPTCHA verification helper.
 */

declare(strict_types=1);

namespace App\Services;

final class RecaptchaVerifier
{
    public static function verify(?string $secretKey, ?string $responseToken, ?string $remoteIp): bool
    {
        $secretKey = trim((string) $secretKey);
        $responseToken = trim((string) $responseToken);

        if ($secretKey === '') {
            // If no secret is configured, do not block forms. This lets dev/staging work.
            return true;
        }

        if ($responseToken === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secretKey,
            'response' => $responseToken,
            'remoteip' => $remoteIp ?? '',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 4,
            ],
        ]);

        $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        if ($raw === false) {
            return false;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && !empty($decoded['success']);
    }
}

// End of file.
