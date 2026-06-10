<?php

declare(strict_types=1);

namespace App\Tenant\Sales;

use RuntimeException;

/**
 * Minimal Stripe Checkout client using form-encoded REST calls.
 */
final class StripeCheckoutService
{
    public function createSession(string $secretKey, array $order, array $items, string $successUrl, string $cancelUrl, ?string $connectedAccountId, int $applicationFeeCents): array
    {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }

        $payload = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $order['order_number'],
            'metadata[artsfolio_order_id]' => (string) $order['id'],
            'metadata[artsfolio_tenant_id]' => (string) $order['tenant_id'],
            'payment_intent_data[metadata][artsfolio_order_id]' => (string) $order['id'],
            'payment_intent_data[metadata][artsfolio_tenant_id]' => (string) $order['tenant_id'],
            'shipping_address_collection[allowed_countries][0]' => 'US',
            'shipping_address_collection[allowed_countries][1]' => 'CA',
        ];

        if ($connectedAccountId !== null && $connectedAccountId !== '') {
            $payload['payment_intent_data[transfer_data][destination]'] = $connectedAccountId;
            if ($applicationFeeCents > 0) {
                $payload['payment_intent_data[application_fee_amount]'] = (string) $applicationFeeCents;
            }
        }

        foreach (array_values($items) as $index => $item) {
            $payload["line_items[{$index}][quantity]"] = (string) max(1, (int) $item['quantity']);
            $payload["line_items[{$index}][price_data][currency]"] = 'usd';
            $payload["line_items[{$index}][price_data][unit_amount]"] = (string) max(50, (int) $item['unit_price_cents']);
            $payload["line_items[{$index}][price_data][product_data][name]"] = (string) $item['title_snapshot'];
        }

        $body = http_build_query($payload);
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeException('Stripe request failed: ' . curl_error($ch));
            }
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
            $raw = file_get_contents('https://api.stripe.com/v1/checkout/sessions', false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\s(\d{3})\s/', $statusLine, $match);
            $status = isset($match[1]) ? (int) $match[1] : 0;
        }

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Stripe checkout session failed: ' . substr((string) $raw, 0, 500));
        }
        if (empty($decoded['id']) || empty($decoded['url'])) {
            throw new RuntimeException('Stripe checkout response did not include a session URL.');
        }

        return $decoded;
    }
}

// End of file.
