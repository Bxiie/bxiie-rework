<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Signup\SignupCodeRepository;
use App\Support\Flash\FlashMessages;
use PDO;
use Throwable;

/**
 * Shows tenant admins the selected pricing tier and current feature usage.
 */
final class BillingController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $plan = $this->currentPlan($tenant) ?? $this->fallbackPlan();
        $plans = $this->plans();
        $usage = $this->usage($tenant);
        $featureRows = $this->featureRows($plan, $usage, $tenant);
        $planOptions = '';
        foreach ($plans as $candidate) {
            $slug = $this->e((string) $candidate['slug']);
            $name = $this->e((string) $candidate['name']);
            $price = $this->money((int) ($candidate['monthly_price_cents'] ?? 0));
            $selected = (string) $candidate['slug'] === (string) $plan['slug'] ? ' selected' : '';
            $planOptions .= "<option value=\"{$slug}\"{$selected}>{$name} — {$price}</option>";
        }

        $economics = $this->salesEconomics($plan);
        $commission = $economics['commission_label'];
        $cardFees = $economics['card_label'];
        $payout100 = $this->payoutExample(10000, $economics);
        $payout1000 = $this->payoutExample(100000, $economics);
        $complementary = $this->isComplementary($tenant) ? '<p class="admin-notice admin-notice-info"><strong>Complementary plan:</strong> platform service billing is waived. Platform commission and credit card charges still apply to sales.</p>' : '';
        $csrf = $this->e($this->csrfToken());
        $freeAccessPlanOptions = $this->freeAccessPlanOptions($plans, (string) $plan['slug']);
        $planName = $this->e((string) $plan['name']);
        $price = $this->money((int) ($plan['monthly_price_cents'] ?? 0));
        $summary = $this->e((string) ($plan['description'] ?? 'ArtsFolio artist portfolio plan.'));
        $billing = $this->billingDetails($tenant);
        $billingPanel = $this->billingDetailsPanel($billing);
        $planChangeHelp = $this->planChangeHelp($plan, $billing);

        $body = <<<HTML
<section class="admin-billing-summary">
  <div class="admin-panel">
    <p class="admin-muted">Current pricing tier</p>
    <h2>{$planName}</h2>
    <p class="billing-price">{$price}</p>
    <p>{$summary}</p>
    <p><strong>Platform sales commission:</strong> {$this->e($commission)}</p>
    <p><strong>Credit card charges:</strong> {$this->e($cardFees)}</p>
    <div class="admin-panel-subtle"><h3>Estimated seller proceeds</h3><p>On a $100 sale, estimated payout is <strong>{$this->e($payout100)}</strong>.</p><p>On a $1,000 sale, estimated payout is <strong>{$this->e($payout1000)}</strong>.</p><p class="admin-muted">Seller receives sale amount minus platform commission, minus credit card percentage, minus fixed credit card charge. Shipping, tax, refunds, and chargebacks are not included in this estimate.</p></div>
    {$complementary}
  </div>
  <div class="admin-panel">
    <p class="admin-muted">Change plan</p>
    <form class="plan-edit-form" method="post" action="/admin/billing/plan" class="admin-form">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <label>Plan<select name="plan_slug">{$planOptions}</select></label>
      <p class="admin-notice admin-notice-warning">{$planChangeHelp}</p>
      <label class="admin-checkbox-card"><input type="checkbox" name="understand_billing" value="1" required><span><strong>I understand the billing effect of this plan change.</strong><small>Paid upgrades and paid signup require card details and immediate billing. Downgrades and cancellations keep current-plan access until the billing recurrence date.</small></span></label>
      <label>Type CHANGE PLAN to confirm<input type="text" name="billing_confirmation" pattern="CHANGE PLAN" autocomplete="off" required></label>
      <button type="submit">Confirm plan change</button>
    </form>
  </div>
  {$billingPanel}
  <div class="admin-panel">
    <p class="admin-muted">Apply free access code</p>
    <form method="post" action="/admin/billing/free-access-code" class="admin-form">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <label>Free access code<input type="text" name="signup_code" autocomplete="off" required></label>
      <label>Plan for free access<select name="plan_slug" required>{$freeAccessPlanOptions}</select></label>
      <button type="submit">Apply free access</button>
    </form>
    <p class="admin-muted">Free access codes can move this tenant onto any active plan for the number of months configured by platform admin. Card fees, commissions, shipping, and taxes are not waived.</p>
  </div>
  <div class="admin-panel">
    <p class="admin-muted">Payment method</p>
    <form method="post" action="/admin/billing/portal" class="admin-form">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <button type="submit">Update payment method</button>
    </form>
    <p class="admin-muted">Opens Stripe Billing Portal to update card details, view invoices, or resolve failed payments.</p>
  </div>

</section>

<section class="admin-panel admin-panel-wide">
  <h2>Feature usage by selected pricing tier</h2>
  <p class="admin-muted">These features match the platform-admin pricing setup fields.</p>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Feature</th><th>Included</th><th>Used</th><th>Status</th></tr></thead>
    <tbody>{$featureRows}</tbody>
  </table></div>
</section>
HTML;

        return Response::html(AdminLayout::render('Billing', $body));
    }

    public function updatePlan(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'owner'])) {
            return Response::html('<h1>Forbidden</h1><p>Only tenant owners may change plans.</p>', 403);
        }
        if (!$this->validCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }
        if (!isset($_POST['understand_billing']) || strtoupper(trim((string) ($_POST['billing_confirmation'] ?? ''))) !== 'CHANGE PLAN') {
            return Response::html('<h1>Confirmation required</h1><p>Type CHANGE PLAN and confirm that you understand the billing effect before changing plans.</p>', 422);
        }
        $slug = strtolower(trim((string) ($_POST['plan_slug'] ?? '')));
        $targetPlan = $this->planBySlug($slug);
        if (!$targetPlan) {
            return Response::html('<h1>Invalid plan</h1>', 422);
        }
        $currentPlan = $this->currentPlan($tenant) ?? $this->fallbackPlan();
        if ((string) ($currentPlan['slug'] ?? '') === (string) ($targetPlan['slug'] ?? '')) {
            FlashMessages::success('No billing plan change was needed.');
            return new Response('', 303, ['Location' => '/admin/billing?notice=plan-unchanged']);
        }
        $currentPrice = max(0, (int) ($currentPlan['monthly_price_cents'] ?? 0));
        $targetPrice = max(0, (int) ($targetPlan['monthly_price_cents'] ?? 0));
        $billing = $this->billingDetails($tenant);
        $recurrence = $this->recurrenceDate($billing);
        if ($targetPrice === 0 || ($currentPrice > 0 && $targetPrice < $currentPrice)) {
            $this->schedulePlanChange($tenant, $targetPlan, $targetPrice === 0 ? 'cancel' : 'downgrade', $recurrence);
            FlashMessages::success('Plan change scheduled. You keep current-plan features until ' . $this->dateLabel($recurrence) . '.');
            return new Response('', 303, ['Location' => '/admin/billing?notice=plan-scheduled']);
        }
        $prorationCents = $currentPrice > 0 ? $this->prorationCents($currentPrice, $targetPrice, $recurrence) : 0;
        if ($currentPrice > 0 && $targetPrice > $currentPrice && $this->canUpdateStripeSubscriptionPrice($billing, $targetPlan)) {
            try {
                $update = $this->updateStripeSubscriptionPrice($billing, $targetPlan);
                $this->recordStripeSubscriptionPriceUpdate($tenant, $targetPlan, $update);
                FlashMessages::success('Plan upgraded and Stripe was instructed to invoice the prorated difference immediately.');
                return new Response('', 303, ['Location' => '/admin/billing?notice=plan-upgraded']);
            } catch (Throwable $e) {
                return Response::html('<h1>Could not update Stripe subscription</h1><p>' . $this->e($e->getMessage()) . '</p>', 422);
            }
        }
        $this->recordPendingPaidPlanChange($tenant, $targetPlan, $currentPrice === 0 ? 'paid_start' : 'upgrade', $prorationCents);
        try {
            $session = $this->createBillingCheckoutSession($request, $tenant, $targetPlan, $currentUser, $prorationCents);
            $this->recordStripeCheckoutSession($tenant, (int) $targetPlan['id'], (string) $session['id'], $prorationCents);
            return new Response('', 303, ['Location' => (string) $session['url']]);
        } catch (Throwable $e) {
            return Response::html('<h1>Could not start billing checkout</h1><p>' . $this->e($e->getMessage()) . '</p>', 422);
        }
    }

    public function managePayment(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'owner'])) {
            return Response::html('<h1>Forbidden</h1><p>Only tenant owners may manage payment methods.</p>', 403);
        }
        if (!$this->validCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $assignment = $this->latestBillingAssignment($tenant);
        $customerId = trim((string) ($assignment['stripe_customer_id'] ?? ''));
        if ($customerId === '') {
            FlashMessages::error('Stripe customer details are not available yet. Start or complete paid checkout first.');
            return new Response('', 303, ['Location' => '/admin/billing?notice=missing-stripe-customer']);
        }

        try {
            $service = new \App\Platform\Billing\StripeBillingPortalService();
            $session = $service->createSession(
                $this->platformSetting('stripe_secret_key', ''),
                $customerId,
                $this->absoluteTenantUrl($request, '/admin/billing?notice=payment-method-return'),
                $this->platformSetting('stripe_billing_portal_configuration_id', null)
            );
            $this->recordBillingPortalSession($tenant, (string) $session['id']);
        } catch (Throwable $e) {
            $this->recordBillingPortalError($tenant, $e->getMessage());
            FlashMessages::error('Could not open Stripe Billing Portal: ' . $e->getMessage());
            return new Response('', 303, ['Location' => '/admin/billing?notice=portal-error']);
        }

        return new Response('', 303, ['Location' => (string) $session['url']]);
    }



    public function applyFreeAccessCode(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'owner'])) {
            return Response::html('<h1>Forbidden</h1><p>Only tenant owners may apply billing codes.</p>', 403);
        }
        if (!$this->validCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $code = strtoupper(trim((string) ($_POST['signup_code'] ?? '')));
        $planSlug = strtolower(trim((string) ($_POST['plan_slug'] ?? '')));
        $email = strtolower(trim((string) ($currentUser['email'] ?? '')));
        if ($email === '') {
            return Response::html('<h1>Current user email unavailable</h1>', 422);
        }

        try {
            $codes = new SignupCodeRepository($this->pdo);
            $signupCode = $codes->validateFreeAccessForExistingTenant($code, $email);
            $plan = $this->planBySlug($planSlug);
            if (!$plan) {
                return Response::html('<h1>Invalid plan</h1>', 422);
            }
            $this->applyFreeAccessPlan($tenant, (int) $plan['id'], $signupCode);
            $codes->markRedeemed((int) $signupCode['id'], $tenant->tenantId, $email);
            $this->setSetting($tenant, 'billing_plan', (string) $plan['slug']);
        } catch (Throwable $e) {
            return Response::html('<h1>Could not apply free access code</h1><p>' . $this->e($e->getMessage()) . '</p>', 422);
        }

        FlashMessages::success('Free access code applied.');
        return new Response('', 303, ['Location' => '/admin/billing?notice=free-access-applied']);
    }


    /** @return array<string,mixed> */
    private function billingDetails(TenantContext $tenant): array
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT tpa.*, p.name AS plan_name, p.slug AS plan_slug, p.monthly_price_cents AS plan_monthly_price_cents FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id ORDER BY tpa.id DESC LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function billingDetailsPanel(array $billing): string
    {
        if ($billing === []) {
            return '<div class="admin-panel"><p class="admin-muted">Billing details</p><p>No subscription billing record exists yet.</p></div>';
        }
        $status = $this->e((string) ($billing['billing_status'] ?? $billing['status'] ?? 'manual'));
        $recurs = $this->e($this->dateLabel($this->recurrenceDate($billing)));
        $subscription = $this->e((string) ($billing['stripe_subscription_id'] ?? '')) ?: 'Not connected';
        $payment = trim((string) ($billing['stripe_payment_method_brand'] ?? '') . ' ' . (string) ($billing['stripe_payment_method_last4'] ?? ''));
        $payment = $payment !== '' ? $this->e($payment) : 'No saved card details yet';
        $pending = trim((string) ($billing['pending_change_type'] ?? '')) !== '' ? '<p><strong>Pending change:</strong> ' . $this->e((string) $billing['pending_change_type']) . ' to ' . $this->e((string) ($billing['pending_plan_slug'] ?? 'selected plan')) . ' on ' . $this->e($this->dateLabel((string) ($billing['pending_effective_at'] ?? ''))) . '.</p>' : '<p><strong>Pending change:</strong> None</p>';
        return <<<HTML
  <div class="admin-panel">
    <p class="admin-muted">Billing details</p>
    <p><strong>Billing status:</strong> {$status}</p>
    <p><strong>Recurring billing date:</strong> {$recurs}</p>
    <p><strong>Stripe subscription:</strong> {$subscription}</p>
    <p><strong>Stripe subscription item:</strong> {$this->e((string) ($billing['stripe_subscription_item_id'] ?? ''))}</p>
    <p><strong>Payment method:</strong> {$payment}</p>
    {$pending}
  </div>
HTML;
    }

    private function planChangeHelp(array $currentPlan, array $billing): string
    {
        $recurs = $this->dateLabel($this->recurrenceDate($billing));
        return 'Paid plans require card details. A paid upgrade updates the Stripe subscription with the target plan Price ID and bills the prorated difference for the days remaining in this billing month immediately when a Stripe subscription item is known; otherwise it starts Stripe Checkout. Moving from Free to a paid plan bills the new plan immediately. Downgrades and cancellations keep current-plan features until ' . $this->e($recurs) . '.';
    }

    private function recurrenceDate(array $billing): string
    {
        $value = trim((string) ($billing['current_period_ends_at'] ?? ''));
        return $value !== '' ? $value : (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 month')->format('Y-m-d H:i:s');
    }

    private function prorationCents(int $currentPriceCents, int $targetPriceCents, string $periodEnd): int
    {
        $difference = max(0, $targetPriceCents - $currentPriceCents);
        if ($difference === 0) { return 0; }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        try { $end = new \DateTimeImmutable($periodEnd, new \DateTimeZone('UTC')); } catch (Throwable) { $end = $now->modify('+1 month'); }
        return (int) max(0, round($difference * min(1, max(0, $end->getTimestamp() - $now->getTimestamp()) / (30 * 86400))));
    }

    private function schedulePlanChange(TenantContext $tenant, array $targetPlan, string $changeType, string $effectiveAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenant_plan_assignments SET pending_plan_id = :plan_id, pending_plan_slug = :plan_slug, pending_change_type = :change_type, pending_effective_at = :effective_at, pending_proration_cents = 0, cancel_at_period_end = :cancel, billing_note = :billing_note WHERE tenant_id = :tenant_id');
        $stmt->execute(['plan_id' => (int) $targetPlan['id'], 'plan_slug' => (string) $targetPlan['slug'], 'change_type' => $changeType, 'effective_at' => $effectiveAt, 'cancel' => $changeType === 'cancel' ? 1 : 0, 'billing_note' => ucfirst($changeType) . ' scheduled for ' . $effectiveAt . '. Current-plan access remains available until then.', 'tenant_id' => $tenant->tenantId]);

        (new \App\Platform\Billing\BillingNotificationService($this->pdo))
            ->queuePlanChangeScheduled($tenant->tenantId, $targetPlan, $changeType, $effectiveAt);
    }

    private function recordPendingPaidPlanChange(TenantContext $tenant, array $targetPlan, string $changeType, int $prorationCents): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO tenant_plan_assignments (tenant_id, plan_id, status, billing_status, pending_plan_id, pending_plan_slug, pending_change_type, pending_effective_at, pending_proration_cents, billing_note, created_at) VALUES (:tenant_id, :plan_id, "active", "payment_pending", :pending_plan_id, :pending_plan_slug, :change_type, UTC_TIMESTAMP(), :proration, :billing_note, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE billing_status = "payment_pending", pending_plan_id = VALUES(pending_plan_id), pending_plan_slug = VALUES(pending_plan_slug), pending_change_type = VALUES(pending_change_type), pending_effective_at = VALUES(pending_effective_at), pending_proration_cents = VALUES(pending_proration_cents), billing_note = VALUES(billing_note)');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'plan_id' => (int) $targetPlan['id'], 'pending_plan_id' => (int) $targetPlan['id'], 'pending_plan_slug' => (string) $targetPlan['slug'], 'change_type' => $changeType, 'proration' => $prorationCents, 'billing_note' => 'Stripe Checkout started for ' . $changeType . '. Immediate prorated charge: ' . $this->plainMoney($prorationCents) . '.']);
    }

    private function recordStripeCheckoutSession(TenantContext $tenant, int $planId, string $sessionId, int $prorationCents): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenant_plan_assignments SET stripe_checkout_session_id = :session_id, pending_proration_cents = :proration, pending_plan_id = :plan_id WHERE tenant_id = :tenant_id');
        $stmt->execute(['session_id' => $sessionId, 'proration' => $prorationCents, 'plan_id' => $planId, 'tenant_id' => $tenant->tenantId]);
    }

    private function canUpdateStripeSubscriptionPrice(array $billing, array $targetPlan): bool
    {
        return trim((string) ($billing['stripe_subscription_id'] ?? '')) !== ''
            && trim((string) ($billing['stripe_subscription_item_id'] ?? '')) !== ''
            && trim((string) ($targetPlan['stripe_monthly_price_id'] ?? '')) !== '';
    }

    /** @return array<string,mixed> */
    private function updateStripeSubscriptionPrice(array $billing, array $targetPlan): array
    {
        return (new \App\Platform\Billing\StripeSubscriptionCheckoutService())->updateSubscriptionPrice(
            $this->platformSetting('stripe_secret_key'),
            (string) $billing['stripe_subscription_id'],
            (string) $billing['stripe_subscription_item_id'],
            (string) $targetPlan['stripe_monthly_price_id'],
            [
                'artsfolio_billing_plan_id' => (string) $targetPlan['id'],
                'artsfolio_billing_plan_slug' => (string) $targetPlan['slug'],
            ],
            'always_invoice',
        );
    }

    /** @param array<string,mixed> $stripeUpdate */
    private function recordStripeSubscriptionPriceUpdate(TenantContext $tenant, array $targetPlan, array $stripeUpdate): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenant_plan_assignments SET plan_id = :plan_id, billing_status = "active", stripe_subscription_status = :stripe_status, stripe_pending_update_id = :pending_update_id, billing_note = "Stripe subscription price updated with stable Price ID and immediate invoicing requested.", pending_plan_id = NULL, pending_plan_slug = NULL, pending_change_type = NULL, pending_effective_at = NULL, pending_proration_cents = 0 WHERE tenant_id = :tenant_id');
        $stmt->execute([
            'plan_id' => (int) $targetPlan['id'],
            'stripe_status' => (string) ($stripeUpdate['status'] ?? 'active'),
            'pending_update_id' => isset($stripeUpdate['pending_update']['id']) ? (string) $stripeUpdate['pending_update']['id'] : null,
            'tenant_id' => $tenant->tenantId,
        ]);

        (new \App\Platform\Billing\BillingNotificationService($this->pdo))
            ->queuePlanUpgraded($tenant->tenantId, $targetPlan);
    }

    /** @return array<string,mixed> */
    private function createBillingCheckoutSession(Request $request, TenantContext $tenant, array $targetPlan, ?array $currentUser, int $prorationCents): array
    {
        $secretKey = $this->platformSetting('stripe_secret_key');
        $email = strtolower(trim((string) ($currentUser['email'] ?? '')));
        if ($email === '') { throw new \RuntimeException('Current user email is required before billing checkout can start.'); }
        $baseUrl = 'https://' . $request->host();
        return (new \App\Platform\Billing\StripeSubscriptionCheckoutService())->createSubscriptionSession($secretKey, $tenant->tenantId, $targetPlan, $baseUrl . '/admin/billing?notice=billing-complete', $baseUrl . '/admin/billing?notice=billing-canceled', $email, $prorationCents);
    }

    private function platformSetting(string $key): string
    {
        if (!$this->tableExists('platform_settings')) { return ''; }
        $stmt = $this->pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? '' : (string) $value;
    }

    private function dateLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') { return 'the next billing recurrence date'; }
        try { return (new \DateTimeImmutable($value))->format('F j, Y'); } catch (Throwable) { return $value; }
    }

    /**
     * Count billable custom-domain groups for plan usage.
     */
    private function countCustomDomainGroups(TenantContext $tenant): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT hostname
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND domain_type <> 'subdomain'
               AND hostname NOT LIKE '%.artsfol.io'"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $groups = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hostname) {
            $groups[preg_replace('/^www\./', '', strtolower((string) $hostname))] = true;
        }

        return count($groups);
    }

    /**
     * @return array<string,mixed>
     */
    private function latestBillingAssignment(TenantContext $tenant): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
               FROM tenant_plan_assignments
              WHERE tenant_id = :tenant_id
              ORDER BY id DESC
              LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private function recordBillingPortalSession(TenantContext $tenant, string $sessionId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_plan_assignments
                SET billing_portal_last_session_id = :session_id,
                    billing_portal_last_session_at = UTC_TIMESTAMP(),
                    payment_method_update_requested_at = UTC_TIMESTAMP(),
                    latest_stripe_error = NULL
              WHERE tenant_id = :tenant_id
              ORDER BY id DESC
              LIMIT 1'
        );
        $stmt->execute([
            'session_id' => $sessionId,
            'tenant_id' => $tenant->tenantId,
        ]);
    }

    private function recordBillingPortalError(TenantContext $tenant, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_plan_assignments
                SET latest_stripe_error = :message,
                    payment_method_update_requested_at = UTC_TIMESTAMP()
              WHERE tenant_id = :tenant_id
              ORDER BY id DESC
              LIMIT 1'
        );
        $stmt->execute([
            'message' => substr($message, 0, 2000),
            'tenant_id' => $tenant->tenantId,
        ]);
    }

    // Duplicate platformSetting() removed by repair_artsfolio_billing_portal_duplicate_helpers_20260627.py.

private function absoluteTenantUrl(Request $request, string $path): string
    {
        $proto = $request->server('HTTP_X_FORWARDED_PROTO')
            ?: (($request->server('HTTPS') === 'on' || $request->server('HTTPS') === '1') ? 'https' : 'http');

        return $proto . '://' . $request->host() . $path;
    }



    private function featureRows(array $plan, array $usage, TenantContext $tenant): string
    {
        $features = [
            'artworks' => ['Artwork records', (int) ($plan['allowed_artworks'] ?? 0), $usage['artworks']],
            'storage_gb' => ['Media storage GB', (int) ($plan['allowed_storage_gb'] ?? 0), $usage['storage_gb']],
            'email_signups' => ['Email subscribers', (int) ($plan['allowed_email_addresses'] ?? 0), $usage['email_signups']],
            'contact_messages' => ['Contact messages', (int) ($plan['allowed_contact_messages'] ?? 0), $usage['contact_messages']],
            'custom_domains' => ['Custom domains', (int) ($plan['custom_domain_included'] ?? 0), $usage['custom_domains']],
            'admin_users' => ['Admin users', (int) ($plan['allowed_admin_users'] ?? 0), $usage['admin_users']],
            'sales' => ['Online checkout', ((int) ($plan['allow_sales'] ?? 0) === 1 ? 'Included' : 'Paid-plan setting off'), ((int) ($plan['allow_sales'] ?? 0) === 1 ? 'Available' : 'Unavailable')],
            'platform_commission' => ['Platform sales commission', $this->salesEconomics($plan)['commission_label'], 'Shown at checkout/billing'],
            'credit_card_fees' => ['Credit card charges', $this->salesEconomics($plan)['card_label'], 'Deducted from sales'],
            'directory' => ['Directory/discovery listing', 'Opt-in', $this->truthy($this->setting($tenant, 'platform_directory_opt_in', '0')) ? 'Enabled' : 'Off'],
            'analytics' => ['Analytics events', ((int) ($plan['monthly_price_cents'] ?? 0) === 0 ? 'Basic' : 'Advanced'), $usage['analytics_events']],
        ];

        $rows = '';
        foreach ($features as $key => [$label, $included, $used]) {
            $status = is_numeric($included) ? $this->status((float) $used, (float) $included) : 'OK';
            if ($key === 'sales' && $included !== 'Included') {
                $status = 'Upgrade required';
            }
            $rows .= '<tr><td>' . $this->e((string) $label) . '</td><td>' . $this->e((string) $included) . '</td><td>' . $this->e((string) $used) . '</td><td>' . $this->e($status) . '</td></tr>';
        }

        return $rows;
    }

    private function plans(): array
    {
        if (!$this->tableExists('plans')) {
            return [$this->fallbackPlan()];
        }
        $stmt = $this->pdo->query('SELECT * FROM plans WHERE is_active = 1 ORDER BY display_order ASC, monthly_price_cents ASC, id ASC');
        $plans = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return $plans !== [] ? $plans : [$this->fallbackPlan()];
    }

    private function freeAccessPlanOptions(array $plans, string $currentSlug): string
    {
        $options = '';
        foreach ($plans as $plan) {
            $slug = $this->e((string) $plan['slug']);
            $name = $this->e((string) $plan['name']);
            $price = $this->money((int) ($plan['monthly_price_cents'] ?? 0));
            $selected = (string) $plan['slug'] === $currentSlug ? ' selected' : '';
            $options .= '<option value="' . $slug . '"' . $selected . '>' . $name . ' — ' . $price . '</option>';
        }

        return $options;
    }

    private function currentPlan(TenantContext $tenant): ?array
    {
        if ($this->tableExists('tenant_plan_assignments')) {
            $stmt = $this->pdo->prepare('SELECT p.* FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id AND tpa.status IN ("trial", "active", "manual") ORDER BY tpa.id DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        return $this->planBySlug($this->setting($tenant, 'billing_plan', 'studio'));
    }

    private function planBySlug(string $slug): ?array
    {
        if (!$this->tableExists('plans')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function assignPlan(TenantContext $tenant, int $planId): void
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO tenant_plan_assignments (tenant_id, plan_id, status, billing_status, current_period_started_at, current_period_ends_at) VALUES (:tenant_id, :plan_id, "manual", "manual", UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 MONTH)) ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), status = "manual", billing_status = VALUES(billing_status), current_period_started_at = COALESCE(current_period_started_at, VALUES(current_period_started_at)), current_period_ends_at = COALESCE(current_period_ends_at, VALUES(current_period_ends_at)), pending_plan_id = NULL, pending_plan_slug = NULL, pending_change_type = NULL, pending_effective_at = NULL, pending_proration_cents = 0, cancel_at_period_end = 0');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'plan_id' => $planId]);
    }

    private function applyFreeAccessPlan(TenantContext $tenant, int $planId, array $signupCode): void
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            return;
        }

        $months = max(1, (int) ($signupCode['free_access_months'] ?? 0));
        $complimentaryUntil = (new \DateTimeImmutable('now'))->modify('+' . $months . ' months')->format('Y-m-d H:i:s');
        $billingNote = 'Free access signup code ' . (string) $signupCode['code'] . ' applied from tenant billing for ' . $months . ' month' . ($months === 1 ? '' : 's') . '.';

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_plan_assignments (tenant_id, plan_id, status, complimentary_until, granted_by_signup_code_id, billing_note, created_at)
             VALUES (:tenant_id, :plan_id, "trial", :complimentary_until, :signup_code_id, :billing_note, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                plan_id = VALUES(plan_id),
                status = "trial",
                complimentary_until = VALUES(complimentary_until),
                granted_by_signup_code_id = VALUES(granted_by_signup_code_id),
                billing_note = VALUES(billing_note)'
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'plan_id' => $planId,
            'complimentary_until' => $complimentaryUntil,
            'signup_code_id' => (int) $signupCode['id'],
            'billing_note' => $billingNote,
        ]);
    }

    private function fallbackPlan(): array
    {
        return ['id' => 0, 'slug' => 'studio', 'name' => 'Studio', 'monthly_price_cents' => 1200, 'description' => 'For active artists.', 'allowed_artworks' => 250, 'allowed_storage_gb' => 5, 'allowed_email_addresses' => 2500, 'allowed_contact_messages' => 250, 'custom_domain_included' => 0, 'allowed_admin_users' => 3, 'allow_sales' => 1, 'credit_card_fee_basis_points' => 290, 'credit_card_fixed_fee_cents' => 30];
    }

    private function usage(TenantContext $tenant): array
    {
        return [
            'artworks' => $this->countRows('artworks', $tenant),
            'storage_gb' => $this->storageGb($tenant),
            'email_signups' => $this->countRows('email_signups', $tenant),
            'contact_messages' => $this->countRows('contact_messages', $tenant),
            'custom_domains' => $this->countCustomDomainGroups($tenant),
            'admin_users' => $this->countMemberships($tenant),
            'analytics_events' => $this->countRows('analytics_events', $tenant),
        ];
    }

    private function salesEconomics(array $plan): array
    {
        $commissionBasisPoints = 500;
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_sales_commission_basis_points' LIMIT 1");
            $stmt->execute();
            $commissionBasisPoints = max(0, min(10000, (int) ($stmt->fetchColumn() ?: 500)));
        } catch (Throwable) {
            // Keep the billing screen available even if platform settings are not ready.
        }

        $cardBasisPoints = max(0, min(10000, (int) ($plan['credit_card_fee_basis_points'] ?? 290)));
        $cardFixedCents = max(0, (int) ($plan['credit_card_fixed_fee_cents'] ?? 30));
        return [
            'commission_basis_points' => $commissionBasisPoints,
            'card_basis_points' => $cardBasisPoints,
            'card_fixed_cents' => $cardFixedCents,
            'commission_label' => number_format($commissionBasisPoints / 100, 2) . '% of platform-processed sales',
            'card_label' => number_format($cardBasisPoints / 100, 2) . '% + $' . number_format($cardFixedCents / 100, 2),
        ];
    }

    private function payoutExample(int $saleCents, array $economics): string
    {
        $commission = (int) round($saleCents * (((int) $economics['commission_basis_points']) / 10000));
        $card = (int) round($saleCents * (((int) $economics['card_basis_points']) / 10000)) + (int) $economics['card_fixed_cents'];
        return $this->plainMoney(max(0, $saleCents - $commission - $card));
    }

    private function plainMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    private function isComplementary(TenantContext $tenant): bool
    {
        if (!$this->columnExists('tenants', 'complementary')) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT complementary FROM tenants WHERE id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        return (int) $stmt->fetchColumn() === 1;
    }

    private function status(float $used, float $limit): string
    {
        if ($limit <= 0 && $used > 0) {
            return 'Upgrade required';
        }
        if ($limit > 0 && $used >= $limit) {
            return 'At or over limit';
        }
        if ($limit > 0 && $used >= $limit * 0.8) {
            return 'Near limit';
        }
        return 'OK';
    }

    private function countRows(string $table, TenantContext $tenant): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countMemberships(TenantContext $tenant): int
    {
        foreach (['tenant_memberships', 'memberships'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE tenant_id = :tenant_id");
                $stmt->execute(['tenant_id' => $tenant->tenantId]);
                return (int) $stmt->fetchColumn();
            } catch (Throwable) {
            }
        }
        return 0;
    }

    private function storageGb(TenantContext $tenant): float
    {
        foreach (['media_assets', 'media'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            foreach (['size_bytes', 'bytes', 'file_size'] as $column) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }
                $stmt = $this->pdo->prepare("SELECT COALESCE(SUM({$column}),0) FROM {$table} WHERE tenant_id = :tenant_id");
                $stmt->execute(['tenant_id' => $tenant->tenantId]);
                return round(((float) $stmt->fetchColumn()) / 1024 / 1024 / 1024, 2);
            }
        }
        return 0.0;
    }

    private function setting(TenantContext $tenant, string $key, string $default = ''): string
    {
        if (!$this->tableExists('tenant_settings')) {
            return $default;
        }
        $stmt = $this->pdo->prepare('SELECT setting_value FROM tenant_settings WHERE tenant_id = :tenant_id AND setting_key = :setting_key LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    private function setSetting(TenantContext $tenant, string $key, string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at) VALUES (:tenant_id, :setting_key, :setting_value, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'setting_key' => $key, 'setting_value' => $value]);
    }

    private function validCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    private function money(int $cents): string
    {
        return $cents === 0 ? '$0' : '$' . number_format($cents / 100, 2) . ' / month';
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $this->pdo->quote($column));
            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (Throwable) {
            return false;
        }
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
