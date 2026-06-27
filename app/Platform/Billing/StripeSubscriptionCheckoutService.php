<?php

declare(strict_types=1);

namespace App\Platform\Billing;

use RuntimeException;

/**
 * Creates Stripe Checkout sessions for ArtsFolio subscription billing.
 */
final class StripeSubscriptionCheckoutService
{
    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public function createSubscriptionSession(
        string $secretKey,
        int $tenantId,
        array $plan,
        string $successUrl,
        string $cancelUrl,
        string $customerEmail,
        int $prorationCents = 0,
    ): array {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }

        $planId = (int) ($plan['id'] ?? 0);
        $planName = trim((string) ($plan['name'] ?? 'ArtsFolio plan'));
        $monthlyCents = max(0, (int) ($plan['monthly_price_cents'] ?? 0));
        if ($planId < 1 || $monthlyCents < 50) {
            throw new RuntimeException('A paid plan with a valid monthly price is required for subscription checkout.');
        }

        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => 'tenant:' . $tenantId . ':plan:' . $planId,
            'customer_email' => $customerEmail,
            'allow_promotion_codes' => 'true',
            'payment_method_collection' => 'always',
            'metadata[artsfolio_billing_tenant_id]' => (string) $tenantId,
            'metadata[artsfolio_billing_plan_id]' => (string) $planId,
            'metadata[artsfolio_billing_plan_slug]' => (string) ($plan['slug'] ?? ''),
            'subscription_data[metadata][artsfolio_billing_tenant_id]' => (string) $tenantId,
            'subscription_data[metadata][artsfolio_billing_plan_id]' => (string) $planId,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => (string) $monthlyCents,
            'line_items[0][price_data][recurring][interval]' => 'month',
            'line_items[0][price_data][product_data][name]' => 'ArtsFolio ' . $planName,
        ];

        if ($prorationCents > 0) {
            $payload['line_items[1][quantity]'] = '1';
            $payload['line_items[1][price_data][currency]'] = 'usd';
            $payload['line_items[1][price_data][unit_amount]'] = (string) max(50, $prorationCents);
            $payload['line_items[1][price_data][product_data][name]'] = 'Immediate prorated ArtsFolio plan change';
        }

        $body = http_build_query($payload);
        $headers = ['Authorization: Bearer ' . $secretKey, 'Content-Type: application/x-www-form-urlencoded'];
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeException('Stripe subscription checkout failed: ' . curl_error($ch));
            }
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("
", $headers), 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
            $raw = file_get_contents('https://api.stripe.com/v1/checkout/sessions', false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\s(\d{3})\s/', $statusLine, $match);
            $status = isset($match[1]) ? (int) $match[1] : 0;
        }

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Stripe subscription checkout session failed: ' . substr((string) $raw, 0, 500));
        }
        if (empty($decoded['id']) || empty($decoded['url'])) {
            throw new RuntimeException('Stripe subscription checkout response did not include a session URL.');
        }
        return $decoded;
    }
}

// End of file.
