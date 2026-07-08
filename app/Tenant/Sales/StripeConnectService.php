<?php

declare(strict_types=1);

namespace App\Tenant\Sales;

use RuntimeException;

/**
 * Minimal Stripe Connect client for tenant payout onboarding.
 *
 * ArtsFolio creates Express connected accounts owned by the artist and then
 * sends the artist to Stripe-hosted onboarding. Stripe remains the source of
 * truth for KYC, bank accounts, payout timing, account restrictions, disputes,
 * and chargebacks. ArtsFolio stores only non-secret account identifiers and
 * readiness flags required for checkout gating and admin status display.
 */
final class StripeConnectService
{
    /**
     * Creates an Express connected account for the artist.
     *
     * @return array<string,mixed>
     */
    public function createExpressAccount(string $secretKey, string $email, string $businessName, int $tenantId, string $tenantSlug, string $country = 'US'): array
    {
        $country = strtoupper(trim($country)) ?: 'US';
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            $country = 'US';
        }

        $payload = [
            'type' => 'express',
            'country' => $country,
            'business_type' => 'individual',
            'capabilities[card_payments][requested]' => 'true',
            'capabilities[transfers][requested]' => 'true',
            'metadata[artsfolio_tenant_id]' => (string) $tenantId,
            'metadata[artsfolio_tenant_slug]' => $tenantSlug,
            'business_profile[name]' => $businessName !== '' ? $businessName : 'ArtsFolio artist',
        ];

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = strtolower($email);
        }

        return $this->stripePost('https://api.stripe.com/v1/accounts', $secretKey, $payload, 'Stripe connected account creation failed');
    }

    /**
     * Creates a Stripe-hosted onboarding link for an existing connected account.
     *
     * @return array<string,mixed>
     */
    public function createOnboardingLink(string $secretKey, string $connectedAccountId, string $refreshUrl, string $returnUrl): array
    {
        $connectedAccountId = trim($connectedAccountId);
        if ($connectedAccountId === '') {
            throw new RuntimeException('A Stripe connected account is required before onboarding can begin.');
        }

        return $this->stripePost('https://api.stripe.com/v1/account_links', $secretKey, [
            'account' => $connectedAccountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ], 'Stripe onboarding link creation failed');
    }

    /**
     * Retrieves current readiness flags for a Stripe connected account.
     *
     * @return array<string,mixed>
     */
    public function retrieveAccount(string $secretKey, string $connectedAccountId): array
    {
        $connectedAccountId = trim($connectedAccountId);
        if ($connectedAccountId === '') {
            throw new RuntimeException('A Stripe connected account ID is required.');
        }

        return $this->stripeGet('https://api.stripe.com/v1/accounts/' . rawurlencode($connectedAccountId), $secretKey, 'Stripe connected account lookup failed');
    }

    /**
     * Returns true only when the connected account can currently accept charges
     * and receive transfers/payouts for destination-charge checkout.
     *
     * @param array<string,mixed> $account
     */
    public function accountReadyForCheckout(array $account): bool
    {
        return !empty($account['charges_enabled']) && !empty($account['payouts_enabled']) && !empty($account['details_submitted']);
    }

    /**
     * Sends a form-encoded POST request to Stripe and decodes the JSON result.
     *
     * @param array<string,string> $payload
     * @return array<string,mixed>
     */
    private function stripePost(string $url, string $secretKey, array $payload, string $failureMessage): array
    {
        $this->assertSecretKey($secretKey);
        $body = http_build_query($payload);
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        [$status, $raw] = $this->request('POST', $url, $headers, $body);
        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException($failureMessage . ': ' . substr((string) $raw, 0, 500));
        }
        if (empty($decoded['id'])) {
            throw new RuntimeException($failureMessage . ': Stripe response did not include an id.');
        }

        return $decoded;
    }

    /**
     * Sends a GET request to Stripe and decodes the JSON result.
     *
     * @return array<string,mixed>
     */
    private function stripeGet(string $url, string $secretKey, string $failureMessage): array
    {
        $this->assertSecretKey($secretKey);
        [$status, $raw] = $this->request('GET', $url, ['Authorization: Bearer ' . $secretKey], null);
        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException($failureMessage . ': ' . substr((string) $raw, 0, 500));
        }

        return $decoded;
    }

    /**
     * Executes a small Stripe HTTP request using cURL when available, with a
     * stream fallback for environments where cURL is absent.
     *
     * @param list<string> $headers
     * @return array{0:int,1:string}
     */
    private function request(string $method, string $url, array $headers, ?string $body): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
            ];
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $body ?? '';
            } else {
                $options[CURLOPT_HTTPGET] = true;
            }
            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new RuntimeException('Stripe request failed: ' . $error);
            }
            curl_close($ch);

            return [$status, (string) $raw];
        }

        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'POST' ? ($body ?? '') : null,
            'timeout' => 20,
            'ignore_errors' => true,
        ]]);
        $raw = file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $match);
        $status = isset($match[1]) ? (int) $match[1] : 0;

        return [$status, (string) $raw];
    }

    private function assertSecretKey(string $secretKey): void
    {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }
    }
}

// End of file.
