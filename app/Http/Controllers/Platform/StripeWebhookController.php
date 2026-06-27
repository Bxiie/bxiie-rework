<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Tenant\Sales\SalesRepository;

/**
 * Receives Stripe Checkout and subscription webhooks.
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

        $eventId = trim((string) ($event['id'] ?? ''));
        if ($eventId === '') {
            return Response::json(['ok' => false, 'error' => 'missing_event_id'], 400);
        }

        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        if (!$this->beginWebhookEvent($eventId, $type, is_array($object) ? $object : [], $payload)) {
            return Response::json(['ok' => true, 'duplicate' => true]);
        }

        try {
            if ($type === 'checkout.session.completed' && is_array($object) && !empty($object['id'])) {
                if ($this->isBillingSession($object)) {
                    $this->markBillingCheckoutCompleted($object);
                } else {
                    $customer = $object['customer_details'] ?? [];
                    $shipping = $object['shipping_details']['address'] ?? null;
                    $this->sales->markPaidByStripeSession(
                        (string) $object['id'],
                        isset($object['payment_intent']) ? (string) $object['payment_intent'] : null,
                        is_array($customer) && isset($customer['email']) ? (string) $customer['email'] : null,
                        is_array($customer) && isset($customer['name']) ? (string) $customer['name'] : null,
                        is_array($shipping) ? $shipping : null,
                    );
                }
            }

            if ($type === 'invoice.paid' && is_array($object)) {
                $this->markBillingInvoicePaid($object);
            }

            if ($type === 'invoice.payment_failed' && is_array($object)) {
                $this->markBillingInvoicePaymentFailed($object);
            }

            if (($type === 'customer.subscription.created' || $type === 'customer.subscription.updated') && is_array($object)) {
                $this->syncBillingSubscriptionUpdated($object);
            }

            if ($type === 'customer.subscription.deleted' && is_array($object)) {
                $this->markBillingSubscriptionDeleted($object);
            }

            $this->finishWebhookEvent($eventId, 200);
            return Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->failWebhookEvent($eventId, $e, 500);
            return Response::json(['ok' => false, 'error' => 'webhook_processing_failed'], 500);
        }
    }

    /** @param array<string,mixed> $session */

    /**
     * Starts an idempotent Stripe webhook event record.
     *
     * Returns false when the event has already been processed or is currently
     * being processed. Failed events may be retried by Stripe.
     *
     * @param array<string,mixed> $object
     */
    private function beginWebhookEvent(string $eventId, string $type, array $object, string $payload): bool
    {
        $objectId = $this->stripeObjectId($object);
        $payloadHash = hash('sha256', $payload);

        try {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO stripe_webhook_events
                    (event_id, event_type, stripe_object_id, payload_hash, payload_json, status, attempt_count, received_at)
                 VALUES
                    (:event_id, :event_type, :stripe_object_id, :payload_hash, :payload_json, "processing", 1, UTC_TIMESTAMP())'
            );
            $stmt->execute([
                'event_id' => $eventId,
                'event_type' => $type,
                'stripe_object_id' => $objectId,
                'payload_hash' => $payloadHash,
                'payload_json' => $payload,
            ]);
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        $stmt = $this->pdo()->prepare(
            'SELECT status
               FROM stripe_webhook_events
              WHERE event_id = :event_id
              LIMIT 1'
        );
        $stmt->execute(['event_id' => $eventId]);
        $status = (string) $stmt->fetchColumn();

        if ($status !== 'failed') {
            return false;
        }

        $stmt = $this->pdo()->prepare(
            'UPDATE stripe_webhook_events
                SET status = "processing",
                    event_type = :event_type,
                    stripe_object_id = :stripe_object_id,
                    payload_hash = :payload_hash,
                    payload_json = :payload_json,
                    attempt_count = attempt_count + 1,
                    response_code = NULL,
                    last_error = NULL,
                    processed_at = NULL
              WHERE event_id = :event_id
                AND status = "failed"'
        );
        $stmt->execute([
            'event_type' => $type,
            'stripe_object_id' => $objectId,
            'payload_hash' => $payloadHash,
            'payload_json' => $payload,
            'event_id' => $eventId,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function finishWebhookEvent(string $eventId, int $responseCode): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE stripe_webhook_events
                SET status = "processed",
                    response_code = :response_code,
                    last_error = NULL,
                    processed_at = UTC_TIMESTAMP()
              WHERE event_id = :event_id'
        );
        $stmt->execute([
            'response_code' => $responseCode,
            'event_id' => $eventId,
        ]);
    }

    private function failWebhookEvent(string $eventId, \Throwable $error, int $responseCode): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE stripe_webhook_events
                SET status = "failed",
                    response_code = :response_code,
                    last_error = :last_error,
                    processed_at = UTC_TIMESTAMP()
              WHERE event_id = :event_id'
        );
        $stmt->execute([
            'response_code' => $responseCode,
            'last_error' => substr($error::class . ": " . $error->getMessage(), 0, 4000),
            'event_id' => $eventId,
        ]);
    }

    /** @param array<string,mixed> $object */
    private function stripeObjectId(array $object): ?string
    {
        $id = trim((string) ($object['id'] ?? ''));
        return $id !== '' ? $id : null;
    }

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
        if ($tenantId < 1 || $planId < 1) {
            return;
        }

        $stmt = $this->pdo()->prepare(
            'UPDATE tenant_plan_assignments
                SET plan_id = :plan_id,
                    status = "active",
                    billing_status = "active",
                    stripe_customer_id = :customer_id,
                    stripe_subscription_id = :subscription_id,
                    stripe_checkout_session_id = :session_id,
                    current_period_started_at = COALESCE(current_period_started_at, UTC_TIMESTAMP()),
                    current_period_ends_at = COALESCE(current_period_ends_at, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 MONTH)),
                    pending_plan_id = NULL,
                    pending_plan_slug = NULL,
                    pending_change_type = NULL,
                    pending_effective_at = NULL,
                    pending_proration_cents = 0,
                    cancel_at_period_end = 0,
                    billing_note = "Stripe subscription checkout completed. Waiting for subscription webhook to record the subscription item ID."
              WHERE tenant_id = :tenant_id'
        );
        $stmt->execute([
            'plan_id' => $planId,
            'customer_id' => isset($session['customer']) ? (string) $session['customer'] : null,
            'subscription_id' => isset($session['subscription']) ? (string) $session['subscription'] : null,
            'session_id' => (string) $session['id'],
            'tenant_id' => $tenantId,
        ]);
    }

    /** @param array<string,mixed> $invoice */
    private function markBillingInvoicePaid(array $invoice): void
    {
        $subscriptionId = (string) ($invoice['subscription'] ?? '');
        if ($subscriptionId === '') {
            return;
        }
        $periodEnd = isset($invoice['lines']['data'][0]['period']['end'])
            ? gmdate('Y-m-d H:i:s', (int) $invoice['lines']['data'][0]['period']['end'])
            : (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 month')->format('Y-m-d H:i:s');

        $stmt = $this->pdo()->prepare(
            'UPDATE tenant_plan_assignments
                SET billing_status = "active",
                    current_period_started_at = UTC_TIMESTAMP(),
                    current_period_ends_at = :period_end,
                    latest_invoice_id = :invoice_id,
                    latest_invoice_url = :invoice_url,
                    latest_invoice_number = :invoice_number,
                    latest_charge_cents = :amount_paid,
                    latest_charge_at = UTC_TIMESTAMP(),
                    last_payment_failed_at = NULL,
                    billing_action_required_at = NULL
              WHERE stripe_subscription_id = :subscription_id'
        );
        $stmt->execute([
            'period_end' => $periodEnd,
            'invoice_id' => (string) ($invoice['id'] ?? ''),
            'invoice_url' => (string) ($invoice['hosted_invoice_url'] ?? ''),
            'invoice_number' => (string) ($invoice['number'] ?? ''),
            'amount_paid' => max(0, (int) ($invoice['amount_paid'] ?? 0)),
            'subscription_id' => $subscriptionId,
        ]);
    }

    /** @param array<string,mixed> $invoice */
    private function markBillingInvoicePaymentFailed(array $invoice): void
    {
        $subscriptionId = (string) ($invoice['subscription'] ?? '');
        if ($subscriptionId === '') {
            return;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE tenant_plan_assignments
                SET billing_status = "past_due",
                    stripe_subscription_status = COALESCE(stripe_subscription_status, "past_due"),
                    latest_invoice_id = :invoice_id,
                    latest_invoice_url = :invoice_url,
                    latest_invoice_number = :invoice_number,
                    last_payment_failed_at = UTC_TIMESTAMP(),
                    billing_action_required_at = UTC_TIMESTAMP(),
                    billing_note = "Stripe reported invoice.payment_failed. Tenant should update payment method."
              WHERE stripe_subscription_id = :subscription_id'
        );
        $stmt->execute([
            'invoice_id' => (string) ($invoice['id'] ?? ''),
            'invoice_url' => (string) ($invoice['hosted_invoice_url'] ?? ''),
            'invoice_number' => (string) ($invoice['number'] ?? ''),
            'subscription_id' => $subscriptionId,
        ]);
    }

    /** @param array<string,mixed> $subscription */
    private function syncBillingSubscriptionUpdated(array $subscription): void
    {
        $subscriptionId = (string) ($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return;
        }
        $metadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
        $planId = (int) ($metadata['artsfolio_billing_plan_id'] ?? 0);
        $tenantId = (int) ($metadata['artsfolio_billing_tenant_id'] ?? 0);
        $status = (string) ($subscription['status'] ?? '');
        $periodEnd = isset($subscription['current_period_end'])
            ? gmdate('Y-m-d H:i:s', (int) $subscription['current_period_end'])
            : null;
        $cancelAt = isset($subscription['cancel_at']) && $subscription['cancel_at'] !== null
            ? gmdate('Y-m-d H:i:s', (int) $subscription['cancel_at'])
            : null;
        $cancelAtPeriodEnd = !empty($subscription['cancel_at_period_end']) ? 1 : 0;
        $subscriptionItemId = $this->subscriptionItemId($subscription);

        $billingStatus = match ($status) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid' => 'past_due',
            'canceled' => 'canceled',
            default => $status !== '' ? $status : 'unknown',
        };

        $sql = 'UPDATE tenant_plan_assignments
                   SET stripe_subscription_status = :subscription_status,
                       billing_status = :billing_status,
                       current_period_ends_at = COALESCE(:period_end, current_period_ends_at),
                       subscription_cancel_at = :cancel_at,
                       cancel_at_period_end = :cancel_at_period_end,
                       stripe_subscription_item_id = COALESCE(:subscription_item_id, stripe_subscription_item_id),
                       billing_action_required_at = CASE
                           WHEN :billing_status_for_action IN ("past_due", "unpaid") THEN UTC_TIMESTAMP()
                           WHEN billing_status IN ("past_due", "unpaid") AND :billing_status_for_clear = "active" THEN NULL
                           ELSE billing_action_required_at
                       END,
                       billing_note = CONCAT("Stripe subscription sync: ", :note_status)';
        $params = [
            'subscription_status' => $status,
            'billing_status' => $billingStatus,
            'period_end' => $periodEnd,
            'cancel_at' => $cancelAt,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'subscription_item_id' => $subscriptionItemId,
            'billing_status_for_action' => $billingStatus,
            'billing_status_for_clear' => $billingStatus,
            'note_status' => $status !== '' ? $status : 'unknown',
            'subscription_id' => $subscriptionId,
        ];
        if ($planId > 0) {
            $sql .= ', plan_id = :plan_id';
            $params['plan_id'] = $planId;
        }
        $sql .= ' WHERE stripe_subscription_id = :subscription_id';
        if ($tenantId > 0) {
            $sql .= ' OR tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    /** @param array<string,mixed> $subscription */
    private function markBillingSubscriptionDeleted(array $subscription): void
    {
        $subscriptionId = (string) ($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE tenant_plan_assignments
                SET billing_status = "canceled",
                    stripe_subscription_status = "canceled",
                    subscription_cancel_at = UTC_TIMESTAMP(),
                    cancel_at_period_end = 0,
                    billing_action_required_at = NULL,
                    billing_note = "Stripe subscription deleted."
              WHERE stripe_subscription_id = :subscription_id'
        );
        $stmt->execute(['subscription_id' => $subscriptionId]);
    }

    /** @param array<string,mixed> $subscription */
    private function subscriptionItemId(array $subscription): ?string
    {
        $items = $subscription['items']['data'] ?? null;
        if (!is_array($items) || !isset($items[0]) || !is_array($items[0])) {
            return null;
        }
        $id = trim((string) ($items[0]['id'] ?? ''));
        return $id !== '' ? $id : null;
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
