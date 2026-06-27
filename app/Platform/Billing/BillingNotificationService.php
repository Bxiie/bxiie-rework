<?php

declare(strict_types=1);

namespace App\Platform\Billing;

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\TemplateRenderer;
use PDO;
use Throwable;

/**
 * Queues tenant-owner billing emails through the existing ArtsFolio outbox.
 *
 * Stripe remains the payment source of truth. This service only translates
 * billing state changes into queued, branded ArtsFolio emails.
 */
final class BillingNotificationService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?EmailOutboxRepository $outbox = null,
        private readonly ?TemplateRenderer $renderer = null,
        private readonly ?string $templateRoot = null,
    ) {
    }

    /** @param array<string,mixed> $session */
    public function queueCheckoutCompletedFromSession(array $session): void
    {
        $tenantId = (int) (($session['metadata']['artsfolio_billing_tenant_id'] ?? 0));
        if ($tenantId < 1) {
            return;
        }
        $plan = $this->planForTenant($tenantId);
        $this->queueTenantOwners($tenantId, 'billing.checkout_completed', 'Your ArtsFolio billing is active', 'checkout-completed.txt', [
            'plan_name' => (string) ($plan['name'] ?? 'your selected plan'),
            'plan_slug' => (string) ($plan['slug'] ?? ''),
            'billing_url' => $this->tenantBillingUrl($tenantId),
        ]);
    }

    /** @param array<string,mixed> $invoice */
    public function queuePaymentFailedFromInvoice(array $invoice): void
    {
        $assignment = $this->assignmentBySubscription((string) ($invoice['subscription'] ?? ''));
        if ($assignment === null) {
            return;
        }
        $this->queueTenantOwners((int) $assignment['tenant_id'], 'billing.payment_failed', 'Action needed: ArtsFolio payment failed', 'payment-failed.txt', [
            'plan_name' => (string) ($assignment['plan_name'] ?? 'your current plan'),
            'invoice_url' => (string) ($invoice['hosted_invoice_url'] ?? ''),
            'invoice_number' => (string) ($invoice['number'] ?? ''),
            'billing_url' => $this->tenantBillingUrl((int) $assignment['tenant_id']),
        ]);
    }

    /** @param array<string,mixed> $invoice */
    public function queuePaymentRecoveredFromInvoice(array $invoice): void
    {
        $assignment = $this->assignmentBySubscription((string) ($invoice['subscription'] ?? ''));
        if ($assignment === null) {
            return;
        }
        $this->queueTenantOwners((int) $assignment['tenant_id'], 'billing.payment_recovered', 'Your ArtsFolio payment is back on track', 'payment-recovered.txt', [
            'plan_name' => (string) ($assignment['plan_name'] ?? 'your current plan'),
            'invoice_url' => (string) ($invoice['hosted_invoice_url'] ?? ''),
            'invoice_number' => (string) ($invoice['number'] ?? ''),
            'amount' => $this->money((int) ($invoice['amount_paid'] ?? 0)),
            'billing_url' => $this->tenantBillingUrl((int) $assignment['tenant_id']),
        ]);
    }

    public function subscriptionWasPastDue(string $subscriptionId): bool
    {
        $assignment = $this->assignmentBySubscription($subscriptionId);
        if ($assignment === null) {
            return false;
        }
        return in_array((string) ($assignment['billing_status'] ?? ''), ['past_due', 'unpaid'], true)
            || trim((string) ($assignment['billing_action_required_at'] ?? '')) !== '';
    }

    /** @param array<string,mixed> $subscription */
    public function queueSubscriptionCanceledFromSubscription(array $subscription): void
    {
        $assignment = $this->assignmentBySubscription((string) ($subscription['id'] ?? ''));
        if ($assignment === null) {
            return;
        }
        $this->queueTenantOwners((int) $assignment['tenant_id'], 'billing.subscription_canceled', 'Your ArtsFolio subscription was canceled', 'subscription-canceled.txt', [
            'plan_name' => (string) ($assignment['plan_name'] ?? 'your previous plan'),
            'billing_url' => $this->tenantBillingUrl((int) $assignment['tenant_id']),
        ]);
    }

    /** @param array<string,mixed> $targetPlan */
    public function queuePlanChangeScheduled(int $tenantId, array $targetPlan, string $changeType, string $effectiveAt): void
    {
        $subject = $changeType === 'cancel' ? 'Your ArtsFolio cancellation is scheduled' : 'Your ArtsFolio plan change is scheduled';
        $this->queueTenantOwners($tenantId, 'billing.plan_change_scheduled', $subject, 'plan-change-scheduled.txt', [
            'change_type' => $changeType,
            'plan_name' => (string) ($targetPlan['name'] ?? $targetPlan['slug'] ?? 'selected plan'),
            'plan_slug' => (string) ($targetPlan['slug'] ?? ''),
            'effective_at' => $effectiveAt,
            'billing_url' => $this->tenantBillingUrl($tenantId),
        ]);
    }

    public function queuePlanChangeApplied(int $tenantId, string $changeType, int $planId): void
    {
        $plan = $this->planById($planId);
        $subject = $changeType === 'cancel' ? 'Your ArtsFolio cancellation is complete' : 'Your ArtsFolio plan change is complete';
        $this->queueTenantOwners($tenantId, 'billing.plan_change_applied', $subject, 'plan-change-applied.txt', [
            'change_type' => $changeType,
            'plan_name' => (string) ($plan['name'] ?? 'your current plan'),
            'plan_slug' => (string) ($plan['slug'] ?? ''),
            'billing_url' => $this->tenantBillingUrl($tenantId),
        ]);
    }

    /** @param array<string,mixed> $targetPlan */
    public function queuePlanUpgraded(int $tenantId, array $targetPlan): void
    {
        $this->queueTenantOwners($tenantId, 'billing.plan_upgraded', 'Your ArtsFolio plan was upgraded', 'plan-upgraded.txt', [
            'plan_name' => (string) ($targetPlan['name'] ?? $targetPlan['slug'] ?? 'your upgraded plan'),
            'plan_slug' => (string) ($targetPlan['slug'] ?? ''),
            'billing_url' => $this->tenantBillingUrl($tenantId),
        ]);
    }

    /** @param array<string,string> $values */
    private function queueTenantOwners(int $tenantId, string $templateKey, string $subject, string $templateName, array $values): void
    {
        if (!$this->tableExists('email_outbox')) {
            return;
        }
        $recipients = $this->tenantBillingRecipients($tenantId);
        if ($recipients === []) {
            return;
        }
        $body = $this->renderTemplate($templateName, $tenantId, $values);
        foreach ($recipients as $recipient) {
            try {
                $this->outbox()->queue(
                    recipientEmail: (string) $recipient['email'],
                    subject: $subject,
                    bodyText: $body,
                    recipientName: $recipient['display_name'] !== null ? (string) $recipient['display_name'] : null,
                    tenantId: $tenantId,
                    userId: (int) $recipient['id'],
                    templateKey: $templateKey,
                );
            } catch (Throwable $e) {
                error_log('Billing notification queue failed for tenant ' . $tenantId . ': ' . $e->getMessage());
            }
        }
    }

    /** @return list<array{id:int,email:string,display_name:?string}> */
    private function tenantBillingRecipients(int $tenantId): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }
        $recipientSql = [];
        if ($this->tableExists('role_assignments') && $this->tableExists('roles')) {
            $recipientSql[] = "SELECT ra.user_id FROM role_assignments ra JOIN roles r ON r.id = ra.role_id WHERE ra.tenant_id = :tenant_id AND r.scope = 'tenant' AND r.slug IN ('tenant_owner', 'owner')";
        }
        if ($this->tableExists('tenant_users')) {
            $recipientSql[] = "SELECT tu.user_id FROM tenant_users tu WHERE tu.tenant_id = :tenant_id AND tu.status = 'active' AND tu.role IN ('owner', 'admin')";
        }
        if ($recipientSql === [] && $this->tableExists('tenant_memberships')) {
            $recipientSql[] = "SELECT tm.user_id FROM tenant_memberships tm WHERE tm.tenant_id = :tenant_id AND tm.status = 'active'";
        }
        if ($recipientSql === []) {
            return [];
        }
        $sql = 'SELECT DISTINCT u.id, u.email, u.display_name FROM users u JOIN (' . implode(' UNION ', $recipientSql) . ') recipients ON recipients.user_id = u.id WHERE u.email IS NOT NULL AND u.email <> "" ORDER BY u.id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,string> $values */
    private function renderTemplate(string $templateName, int $tenantId, array $values): string
    {
        $tenant = $this->tenant($tenantId);
        $values = array_merge([
            'tenant_name' => (string) ($tenant['name'] ?? 'your ArtsFolio site'),
            'tenant_slug' => (string) ($tenant['slug'] ?? ''),
            'billing_url' => $this->tenantBillingUrl($tenantId),
            'support_email' => 'support@artsfol.io',
        ], $values);
        return $this->renderer()->renderFile($this->templateRoot() . '/' . $templateName, $values);
    }

    /** @return array<string,mixed>|null */
    private function assignmentBySubscription(string $subscriptionId): ?array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '' || !$this->tableExists('tenant_plan_assignments')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT tpa.*, p.name AS plan_name, p.slug AS plan_slug FROM tenant_plan_assignments tpa LEFT JOIN plans p ON p.id = tpa.plan_id WHERE tpa.stripe_subscription_id = :subscription_id LIMIT 1');
        $stmt->execute(['subscription_id' => $subscriptionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed> */
    private function planForTenant(int $tenantId): array
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT p.* FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /** @return array<string,mixed> */
    private function planById(int $planId): array
    {
        if ($planId < 1 || !$this->tableExists('plans')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $planId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /** @return array<string,mixed> */
    private function tenant(int $tenantId): array
    {
        if (!$this->tableExists('tenants')) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    private function tenantBillingUrl(int $tenantId): string
    {
        $host = '';
        if ($this->tableExists('tenant_domains')) {
            $stmt = $this->pdo->prepare('SELECT hostname FROM tenant_domains WHERE tenant_id = :tenant_id ORDER BY is_primary DESC, FIELD(status, "active", "dns_verified", "pending_dns"), id ASC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId]);
            $host = trim((string) $stmt->fetchColumn());
        }
        if ($host === '') {
            $tenant = $this->tenant($tenantId);
            $host = trim((string) ($tenant['slug'] ?? '')) . '.artsfol.io';
        }
        return 'https://' . $host . '/admin/billing';
    }

    private function money(int $cents): string
    {
        return '$' . number_format(max(0, $cents) / 100, 2);
    }

    private function outbox(): EmailOutboxRepository
    {
        return $this->outbox ?? new EmailOutboxRepository($this->pdo);
    }

    private function renderer(): TemplateRenderer
    {
        return $this->renderer ?? new TemplateRenderer();
    }

    private function templateRoot(): string
    {
        return $this->templateRoot ?? dirname(__DIR__, 3) . '/template/email/billing';
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

// End of file.
