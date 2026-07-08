<?php

declare(strict_types=1);

namespace App\Tenant\Sales;

use RuntimeException;

/**
 * Minimal Stripe Checkout client using form-encoded REST calls.
 */
final class StripeCheckoutService
{
    /**
     * Creates a Stripe Checkout Session from ArtsFolio-owned catalog data.
     *
     * ArtsFolio intentionally sends inline price_data and inline shipping rate
     * data so tenants do not need matching Products, Prices, or Shipping Rates
     * created inside Stripe. Stripe is payment collection, not catalog truth.
     *
     * @param array<string,mixed> $order
     * @param list<array<string,mixed>> $items
     * @return array<string,mixed>
     */
    public function createSession(
        string $secretKey,
        array $order,
        array $items,
        string $successUrl,
        string $cancelUrl,
        ?string $connectedAccountId,
        int $applicationFeeCents,
        int $shippingCents = 0,
        ?string $customerEmail = null,
    ): array {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }

        $currency = strtolower((string) ($order['currency'] ?? 'usd')) ?: 'usd';
        $payload = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => (string) (time() + 1800),
            'client_reference_id' => (string) $order['order_number'],
            'metadata[artsfolio_order_id]' => (string) $order['id'],
            'metadata[artsfolio_tenant_id]' => (string) $order['tenant_id'],
            'metadata[artsfolio_cart_id]' => (string) ($order['cart_id'] ?? ''),
            'metadata[artsfolio_order_number]' => (string) $order['order_number'],
            'payment_intent_data[metadata][artsfolio_order_id]' => (string) $order['id'],
            'payment_intent_data[metadata][artsfolio_tenant_id]' => (string) $order['tenant_id'],
            'payment_intent_data[metadata][artsfolio_cart_id]' => (string) ($order['cart_id'] ?? ''),
            'phone_number_collection[enabled]' => 'true',
            'shipping_address_collection[allowed_countries][0]' => 'US',
            'shipping_address_collection[allowed_countries][1]' => 'CA',
        ];

        $shippingContact = $this->orderShippingContact($order);
        $prefilledCustomerId = $shippingContact !== null
            ? $this->createCheckoutCustomer($secretKey, $order, $shippingContact, $customerEmail)
            : null;
        if ($prefilledCustomerId !== null) {
            // Stripe Checkout can prefill shipping details from an existing
            // Customer object. The same data is also attached to the
            // PaymentIntent so Stripe records match the ArtsFolio order.
            $payload['customer'] = $prefilledCustomerId;
            $payload['customer_update[name]'] = 'auto';
            $payload['customer_update[address]'] = 'auto';
            $payload['customer_update[shipping]'] = 'auto';
            $this->attachPaymentIntentShipping($payload, $shippingContact);
        } elseif ($customerEmail !== null && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $payload['customer_email'] = $customerEmail;
        }

        if ($shippingCents > 0) {
            $payload['shipping_options[0][shipping_rate_data][type]'] = 'fixed_amount';
            $payload['shipping_options[0][shipping_rate_data][fixed_amount][amount]'] = (string) $shippingCents;
            $payload['shipping_options[0][shipping_rate_data][fixed_amount][currency]'] = $currency;
            $payload['shipping_options[0][shipping_rate_data][display_name]'] = 'Standard shipping';
        }

        if ($connectedAccountId !== null && $connectedAccountId !== '') {
            $payload['payment_intent_data[transfer_data][destination]'] = $connectedAccountId;
            if ($applicationFeeCents > 0) {
                $payload['payment_intent_data[application_fee_amount]'] = (string) $applicationFeeCents;
            }
        }

        foreach (array_values($items) as $index => $item) {
            $payload["line_items[{$index}][quantity]"] = (string) max(1, (int) $item['quantity']);
            $payload["line_items[{$index}][price_data][currency]"] = $currency;
            $payload["line_items[{$index}][price_data][unit_amount]"] = (string) max(50, (int) $item['unit_price_cents']);
            $payload["line_items[{$index}][price_data][product_data][name]"] = (string) $item['title_snapshot'];

            $description = $this->variantDescription($item);
            if ($description !== '') {
                $payload["line_items[{$index}][price_data][product_data][description]"] = $description;
            }
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
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\\r\\n", $headers), 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
            $raw = file_get_contents('https://api.stripe.com/v1/checkout/sessions', false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\\s(\\d{3})\\s/', $statusLine, $match);
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

    /**
     * Retrieves a Checkout Session directly from Stripe for success-return reconciliation.
     *
     * Webhooks remain the preferred source of truth, but the browser success
     * return can safely verify a paid Session with Stripe and finalize the
     * matching local order when webhook delivery is delayed or misconfigured.
     *
     * @return array<string,mixed>
     */
    public function retrieveSession(string $secretKey, string $sessionId): array
    {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }
        if (trim($sessionId) === '') {
            throw new RuntimeException('Stripe checkout session ID is required.');
        }

        $url = 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId);
        $headers = [
            'Authorization: Bearer ' . $secretKey,
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_HTTPGET => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeException('Stripe session lookup failed: ' . curl_error($ch));
            }
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 20, 'ignore_errors' => true]]);
            $raw = file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\s(\d{3})\s/', $statusLine, $match);
            $status = isset($match[1]) ? (int) $match[1] : 0;
        }

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Stripe session lookup failed: ' . substr((string) $raw, 0, 500));
        }

        return $decoded;
    }


    /**
     * Build a normalized shipping contact from the local order row.
     *
     * Stripe Checkout Sessions cannot accept arbitrary one-off shipping
     * address fields for prefill. Passing a Customer with shipping details is
     * the supported hosted-checkout path, so ArtsFolio derives that Customer
     * from the order data collected before Stripe Checkout starts.
     *
     * @param array<string,mixed> $order
     * @return array<string,string>|null
     */
    private function orderShippingContact(array $order): ?array
    {
        $decoded = [];
        $raw = trim((string) ($order['shipping_address_json'] ?? ''));
        if ($raw !== '') {
            $candidate = json_decode($raw, true);
            if (is_array($candidate)) {
                $decoded = $candidate;
            }
        }

        $country = strtoupper(trim((string) ($decoded['country'] ?? 'US')));
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            $country = 'US';
        }

        $shipping = [
            'name' => trim((string) ($decoded['name'] ?? ($order['shipping_name'] ?? ($order['customer_name'] ?? '')))),
            'phone' => trim((string) ($decoded['phone'] ?? ($order['shipping_phone'] ?? ''))),
            'line1' => trim((string) ($decoded['line1'] ?? '')),
            'line2' => trim((string) ($decoded['line2'] ?? '')),
            'city' => trim((string) ($decoded['city'] ?? '')),
            'state' => trim((string) ($decoded['state'] ?? '')),
            'postal_code' => trim((string) ($decoded['postal_code'] ?? '')),
            'country' => $country,
        ];

        foreach (['name', 'line1', 'city', 'state', 'postal_code', 'country'] as $key) {
            if ($shipping[$key] === '') {
                return null;
            }
        }

        return $shipping;
    }

    /**
     * Create a Stripe Customer used only to prefill the hosted Checkout page.
     *
     * @param array<string,mixed> $order
     * @param array<string,string> $shipping
     */
    private function createCheckoutCustomer(string $secretKey, array $order, array $shipping, ?string $customerEmail): ?string
    {
        $payload = [
            'name' => $shipping['name'],
            'phone' => $shipping['phone'],
            'address[line1]' => $shipping['line1'],
            'address[city]' => $shipping['city'],
            'address[state]' => $shipping['state'],
            'address[postal_code]' => $shipping['postal_code'],
            'address[country]' => $shipping['country'],
            'shipping[name]' => $shipping['name'],
            'shipping[phone]' => $shipping['phone'],
            'shipping[address][line1]' => $shipping['line1'],
            'shipping[address][city]' => $shipping['city'],
            'shipping[address][state]' => $shipping['state'],
            'shipping[address][postal_code]' => $shipping['postal_code'],
            'shipping[address][country]' => $shipping['country'],
            'metadata[artsfolio_order_id]' => (string) ($order['id'] ?? ''),
            'metadata[artsfolio_tenant_id]' => (string) ($order['tenant_id'] ?? ''),
            'metadata[artsfolio_order_number]' => (string) ($order['order_number'] ?? ''),
            'metadata[artsfolio_checkout_prefill]' => 'true',
        ];

        if ($customerEmail !== null && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $customerEmail;
        } elseif (filter_var((string) ($order['customer_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = (string) $order['customer_email'];
        }
        if ($shipping['line2'] !== '') {
            $payload['address[line2]'] = $shipping['line2'];
            $payload['shipping[address][line2]'] = $shipping['line2'];
        }

        $customer = $this->stripePost('https://api.stripe.com/v1/customers', $secretKey, $payload, 'Stripe customer prefill request failed');

        return isset($customer['id']) && is_string($customer['id']) && $customer['id'] !== '' ? $customer['id'] : null;
    }

    /** @param array<string,string> $shipping @param array<string,string> $payload */
    private function attachPaymentIntentShipping(array &$payload, array $shipping): void
    {
        $payload['payment_intent_data[shipping][name]'] = $shipping['name'];
        $payload['payment_intent_data[shipping][phone]'] = $shipping['phone'];
        $payload['payment_intent_data[shipping][address][line1]'] = $shipping['line1'];
        $payload['payment_intent_data[shipping][address][city]'] = $shipping['city'];
        $payload['payment_intent_data[shipping][address][state]'] = $shipping['state'];
        $payload['payment_intent_data[shipping][address][postal_code]'] = $shipping['postal_code'];
        $payload['payment_intent_data[shipping][address][country]'] = $shipping['country'];
        if ($shipping['line2'] !== '') {
            $payload['payment_intent_data[shipping][address][line2]'] = $shipping['line2'];
        }
    }

    /**
     * Creates a Stripe refund for an ArtsFolio sales PaymentIntent.
     *
     * Destination-charge refunds should reverse connected-account transfer and
     * application fee when Stripe allows it. Older/direct charge shapes may not
     * accept those flags, so the request is retried without them only after a
     * Stripe API error. A successful response is always the source of truth.
     *
     * @return array<string,mixed>
     */
    public function refundPaymentIntent(string $secretKey, string $paymentIntentId, int $amountCents, string $reason = 'requested_by_customer', ?string $idempotencyKey = null): array
    {
        if (trim($secretKey) === '') {
            throw new RuntimeException('Stripe secret key is not configured in platform admin settings.');
        }
        if (trim($paymentIntentId) === '') {
            throw new RuntimeException('Stripe PaymentIntent id is required for a refund.');
        }
        if ($amountCents <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        $payload = [
            'payment_intent' => $paymentIntentId,
            'amount' => (string) $amountCents,
        ];
        if (in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true)) {
            $payload['reason'] = $reason;
        }

        try {
            return $this->stripePost('https://api.stripe.com/v1/refunds', $secretKey, $payload + [
                'reverse_transfer' => 'true',
                'refund_application_fee' => 'true',
            ], $idempotencyKey);
        } catch (RuntimeException $e) {
            return $this->stripePost('https://api.stripe.com/v1/refunds', $secretKey, $payload, $idempotencyKey);
        }
    }

    /**
     * Sends a form-encoded POST request to Stripe and decodes the JSON result.
     *
     * @param array<string,string> $payload
     * @return array<string,mixed>
     */
    private function stripePost(string $url, string $secretKey, array $payload, string $failureMessage = 'Stripe refund request failed', ?string $idempotencyKey = null): array
    {
        $body = http_build_query($payload);
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $headers[] = 'Idempotency-Key: ' . trim($idempotencyKey);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($raw === false) {
                throw new RuntimeException('Stripe request failed: ' . curl_error($ch));
            }
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
            $raw = file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\s(\d{3})\s/', $statusLine, $match);
            $status = isset($match[1]) ? (int) $match[1] : 0;
        }

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException($failureMessage . ': ' . substr((string) $raw, 0, 500));
        }
        if (empty($decoded['id'])) {
            throw new RuntimeException($failureMessage . ': Stripe response did not include an id.');
        }

        return $decoded;
    }

    /** @param array<string,mixed> $item */
    private function variantDescription(array $item): string
    {
        $parts = [];
        foreach (['variant_label_snapshot', 'size_value_snapshot', 'gender_value_snapshot'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '' && $value !== 'Default' && $value !== 'not_applicable') {
                $parts[] = $value;
            }
        }

        return implode(' · ', array_values(array_unique($parts)));
    }
}

// End of file.
