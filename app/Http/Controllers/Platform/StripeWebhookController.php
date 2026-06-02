<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Tenant\Sales\SalesRepository;

/**
 * Receives Stripe Checkout webhooks and updates ArtsFolio sales orders.
 */
final class StripeWebhookController
{
    public function __construct(
        private readonly PlatformSettingsRepository $settings,
        private readonly SalesRepository $sales,
    ) {}

    public function receive(Request $request): Response
    {
        $payload = file_get_contents('php://input') ?: '';
        $secret = trim((string) $this->settings->get('stripe_webhook_secret', ''));
        if ($secret !== '' && !$this->validSignature($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
            return Response::json(['ok' => false, 'error' => 'invalid_signature'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return Response::json(['ok' => false, 'error' => 'invalid_json'], 400);
        }

        if (($event['type'] ?? '') === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            if (is_array($session) && !empty($session['id'])) {
                $customer = $session['customer_details'] ?? [];
                $shipping = $session['shipping_details']['address'] ?? null;
                $this->sales->markPaidByStripeSession(
                    (string) $session['id'],
                    isset($session['payment_intent']) ? (string) $session['payment_intent'] : null,
                    is_array($customer) && isset($customer['email']) ? (string) $customer['email'] : null,
                    is_array($customer) && isset($customer['name']) ? (string) $customer['name'] : null,
                    is_array($shipping) ? $shipping : null,
                );
            }
        }

        return Response::json(['ok' => true]);
    }

    private function validSignature(string $payload, string $header, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if (!$timestamp || $signatures === []) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }
}

// End of file.
