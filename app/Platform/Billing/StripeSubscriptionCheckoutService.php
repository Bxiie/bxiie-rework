<?php

declare(strict_types=1);

namespace App\Platform\Billing;

use RuntimeException;

/**
 * Talks to Stripe for ArtsFolio subscription billing.
 *
 * Paid plans must use durable Stripe monthly Price IDs stored on the plans
 * table. Dynamic Checkout price_data is deliberately not used for the recurring
 * subscription line because later upgrade/downgrade mutations require a stable
 * target Price ID.
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
        $this->assertSecretKey($secretKey);

        $planId = (int) ($plan['id'] ?? 0);
        $monthlyCents = max(0, (int) ($plan['monthly_price_cents'] ?? 0));
        $priceId = trim((string) ($plan['stripe_monthly_price_id'] ?? ''));

        if ($planId < 1 || $monthlyCents < 50) {
            throw new RuntimeException('A paid plan with a valid monthly price is required for subscription checkout.');
        }
        if ($priceId === '') {
            throw new RuntimeException('This paid plan is missing a Stripe monthly Price ID. Configure it in Platform Admin → Pricing before billing tenants.');
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
            'subscription_data[metadata][artsfolio_billing_plan_slug]' => (string) ($plan['slug'] ?? ''),
            'line_items[0][quantity]' => '1',
            'line_items[0][price]' => $priceId,
        ];

        if ($prorationCents > 0) {
            $payload['line_items[1][quantity]'] = '1';
            $payload['line_items[1][price_data][currency]'] = 'usd';
            $payload['line_items[1][price_data][unit_amount]'] = (string) max(50, $prorationCents);
            $payload['line_items[1][price_data][product_data][name]'] = 'Immediate prorated ArtsFolio plan change';
        }

        return $this->request($secretKey, 'POST', 'https://api.stripe.com/v1/checkout/sessions', $payload, 'Stripe subscription checkout session failed');
    }

    /**
     * @param array<string,string> $metadata
     * @return array<string,mixed>
     */
    public function updateSubscriptionPrice(
        string $secretKey,
        string $subscriptionId,
        string $subscriptionItemId,
        string $priceId,
        array $metadata,
        string $prorationBehavior = 'always_invoice',
    ): array {
        $this->assertSecretKey($secretKey);
        $subscriptionId = trim($subscriptionId);
        $subscriptionItemId = trim($subscriptionItemId);
        $priceId = trim($priceId);
        if ($subscriptionId === '' || $subscriptionItemId === '' || $priceId === '') {
            throw new RuntimeException('Stripe subscription ID, subscription item ID, and target Price ID are required.');
        }

        $payload = [
            'items[0][id]' => $subscriptionItemId,
            'items[0][price]' => $priceId,
            'proration_behavior' => $prorationBehavior,
            'payment_behavior' => 'pending_if_incomplete',
            'expand[0]' => 'latest_invoice.payment_intent',
        ];
        foreach ($metadata as $key => $value) {
            $payload['metadata[' . $key . ']'] = $value;
        }

        return $this->request($secretKey, 'POST', 'https://api.stripe.com/v1/subscriptions/' . rawurlencode($subscriptionId), $payload, 'Stripe subscription price update failed');
    }

    /** @return array<string,mixed> */
    public function cancelSubscriptionNow(string $secretKey, string $subscriptionId): array
    {
        $this->assertSecretKey($secretKey);
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            throw new RuntimeException('Stripe subscription ID is required.');
        }

        return $this->request($secretKey, 'DELETE', 'https://api.stripe.com/v1/subscriptions/' . rawurlencode($subscriptionId), [], 'Stripe subscription cancellation failed');
    }

    private function assertSecretKey(string $secretKey): void
    {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }
    }

    /**
     * @param array<string,string|int> $payload
     * @return array<string,mixed>
     */
    private function request(string $secretKey, string $method, string $url, array $payload, string $failurePrefix): array
    {
        $body = http_build_query($payload);
        $headers = ['Authorization: Bearer ' . $secretKey, 'Content-Type: application/x-www-form-urlencoded'];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $method === 'GET' ? null : $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new RuntimeException($failurePrefix . ': ' . $error);
            }
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => implode("
", $headers),
                'content' => $method === 'GET' ? '' : $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ]]);
            $raw = file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\s(\d{3})\s/', $statusLine, $match);
            $status = isset($match[1]) ? (int) $match[1] : 0;
        }

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException($failurePrefix . ': ' . substr((string) $raw, 0, 500));
        }
        return $decoded;
    }
}

// End of file.
