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
                if ($this->isBillingSession($session)) {
                    $this->markBillingCheckoutCompleted($session);
                } else {
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
        }

        if (($event['type'] ?? '') === 'invoice.paid') {
            $invoice = $event['data']['object'] ?? [];
            if (is_array($invoice)) {
                $this->markBillingInvoicePaid($invoice);
            }
        }

        return Response::json(['ok' => true]);
    }


    /** @param array<string,mixed> $session */
    private function isBillingSession(array $session): bool
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        return isset($metadata['artsfolio_billing_tenant_id'], $metadata['artsfolio_billing_plan_id']);
    }

    /** @param array<string,mixed> $session */
    private function markBillingCheckoutCompleted(array $session): void
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $tenantId = (int) ($metadata['artsfolio_billing_tenant_id'] ?? 0);
        $planId = (int) ($metadata['artsfolio_billing_plan_id'] ?? 0);
        if ($tenantId < 1 || $planId < 1) { return; }
        $stmt = $this->pdo()->prepare('UPDATE tenant_plan_assignments SET plan_id = :plan_id, status = "active", billing_status = "active", stripe_customer_id = :customer_id, stripe_subscription_id = :subscription_id, stripe_checkout_session_id = :session_id, current_period_started_at = COALESCE(current_period_started_at, UTC_TIMESTAMP()), current_period_ends_at = COALESCE(current_period_ends_at, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 MONTH)), pending_plan_id = NULL, pending_plan_slug = NULL, pending_change_type = NULL, pending_effective_at = NULL, pending_proration_cents = 0, cancel_at_period_end = 0, billing_note = "Stripe subscription checkout completed." WHERE tenant_id = :tenant_id');
        $stmt->execute(['plan_id' => $planId, 'customer_id' => isset($session['customer']) ? (string) $session['customer'] : null, 'subscription_id' => isset($session['subscription']) ? (string) $session['subscription'] : null, 'session_id' => (string) $session['id'], 'tenant_id' => $tenantId]);
    }

    /** @param array<string,mixed> $invoice */
    private function markBillingInvoicePaid(array $invoice): void
    {
        $subscriptionId = (string) ($invoice['subscription'] ?? '');
        if ($subscriptionId === '') { return; }
        $periodEnd = isset($invoice['lines']['data'][0]['period']['end']) ? gmdate('Y-m-d H:i:s', (int) $invoice['lines']['data'][0]['period']['end']) : (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 month')->format('Y-m-d H:i:s');
        $stmt = $this->pdo()->prepare('UPDATE tenant_plan_assignments SET billing_status = "active", current_period_started_at = UTC_TIMESTAMP(), current_period_ends_at = :period_end, latest_invoice_id = :invoice_id, latest_charge_cents = :amount_paid, latest_charge_at = UTC_TIMESTAMP() WHERE stripe_subscription_id = :subscription_id');
        $stmt->execute(['period_end' => $periodEnd, 'invoice_id' => (string) ($invoice['id'] ?? ''), 'amount_paid' => max(0, (int) ($invoice['amount_paid'] ?? 0)), 'subscription_id' => $subscriptionId]);
    }

    private function pdo(): \PDO
    {
        $ref = new \ReflectionProperty($this->sales, 'pdo');
        $ref->setAccessible(true);
        return $ref->getValue($this->sales);
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
