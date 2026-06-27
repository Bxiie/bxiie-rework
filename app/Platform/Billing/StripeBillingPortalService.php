<?php

declare(strict_types=1);

namespace App\Platform\Billing;

use RuntimeException;

/**
 * Minimal Stripe Billing Portal client using form-encoded REST calls.
 */
final class StripeBillingPortalService
{
    /**
     * @return array<string,mixed>
     */
    public function createSession(string $secretKey, string $customerId, string $returnUrl, ?string $configurationId = null): array
    {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }
        if (trim($customerId) === '') {
            throw new RuntimeException('This tenant does not have a Stripe customer ID yet.');
        }
        if (trim($returnUrl) === '') {
            throw new RuntimeException('Billing portal return URL could not be determined.');
        }

        $payload = [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ];

        if ($configurationId !== null && trim($configurationId) !== '') {
            $payload['configuration'] = trim($configurationId);
        }

        $decoded = $this->postForm('https://api.stripe.com/v1/billing_portal/sessions', $secretKey, $payload);

        if (empty($decoded['id']) || empty($decoded['url'])) {
            throw new RuntimeException('Stripe Billing Portal response did not include a session URL.');
        }

        return $decoded;
    }

    /**
     * @param array<string,string> $payload
     * @return array<string,mixed>
     */
    private function postForm(string $url, string $secretKey, array $payload): array
    {
        $body = http_build_query($payload);
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeException('Stripe Billing Portal request failed: ' . curl_error($ch));
            }
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $body,
                    'timeout' => 20,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\s(\d{3})\s/', $statusLine, $match);
            $status = isset($match[1]) ? (int) $match[1] : 0;
        }

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Stripe Billing Portal session failed: ' . substr((string) $raw, 0, 500));
        }

        return $decoded;
    }
}

// End of file.
