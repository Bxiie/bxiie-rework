<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;
use PDO;
use Throwable;

/**
 * Read-only Stripe billing configuration validator.
 *
 * This page answers "is billing configured correctly enough to charge money?"
 * without exposing full secrets or making changes.
 */
final class BillingConfigurationController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $checks = $this->checks();
        $summary = $this->summaryCards($checks);
        $rows = $this->checkRows($checks);
        $planRows = $this->planRows();

        return Response::html(AdminLayout::render(
            title: 'Billing Configuration',
            active: 'billing_configuration',
            body: <<<HTML
<p class="admin-muted">Read-only Stripe setup validation. This page checks key presence, key format, live/test consistency, webhook secret setup, Billing Portal configuration format, and plan Price-ID readiness.</p>
{$summary}
<h2>Configuration checks</h2>
<table class="admin-table">
    <thead><tr><th>Status</th><th>Check</th><th>Details</th><th>Fix</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
<h2>Plan Stripe Price-ID readiness</h2>
<table class="admin-table">
    <thead><tr><th>Plan</th><th>Monthly</th><th>Stripe product</th><th>Stripe monthly price</th><th>Status</th></tr></thead>
    <tbody>{$planRows}</tbody>
</table>
<p class="admin-muted">Secrets are intentionally masked. Configure values in Platform Admin → Platform Settings and Stripe Price IDs in Platform Admin → Plans &amp; Billing.</p>
HTML,
        ));
    }

    /**
     * @return list<array{status:string,label:string,details:string,fix:string}>
     */
    private function checks(): array
    {
        $publishable = $this->setting('stripe_publishable_key');
        $secret = $this->setting('stripe_secret_key');
        $webhookSecret = $this->setting('stripe_webhook_secret');
        $portalConfiguration = $this->setting('stripe_billing_portal_configuration_id');

        $publishableMode = $this->stripeKeyMode($publishable, 'pk');
        $secretMode = $this->stripeKeyMode($secret, 'sk');

        $checks = [];

        $checks[] = [
            'status' => $publishable === '' ? 'CRIT' : ($publishableMode === null ? 'WARN' : 'OK'),
            'label' => 'Stripe publishable key',
            'details' => $publishable === '' ? 'Missing' : $this->masked($publishable) . ' (' . ($publishableMode ?? 'unexpected format') . ')',
            'fix' => 'Set stripe_publishable_key to pk_test_... or pk_live_... in Platform Settings.',
        ];

        $checks[] = [
            'status' => $secret === '' ? 'CRIT' : ($secretMode === null ? 'WARN' : 'OK'),
            'label' => 'Stripe secret key',
            'details' => $secret === '' ? 'Missing' : $this->masked($secret) . ' (' . ($secretMode ?? 'unexpected format') . ')',
            'fix' => 'Set stripe_secret_key to sk_test_... or sk_live_... in Platform Settings.',
        ];

        $checks[] = [
            'status' => $webhookSecret === '' ? 'CRIT' : (str_starts_with($webhookSecret, 'whsec_') ? 'OK' : 'WARN'),
            'label' => 'Stripe webhook signing secret',
            'details' => $webhookSecret === '' ? 'Missing' : $this->masked($webhookSecret),
            'fix' => 'Set stripe_webhook_secret to the whsec_... value from Stripe Developers → Webhooks.',
        ];

        $checks[] = [
            'status' => ($publishableMode !== null && $secretMode !== null && $publishableMode !== $secretMode) ? 'CRIT' : 'OK',
            'label' => 'Stripe live/test key consistency',
            'details' => 'Publishable mode: ' . ($publishableMode ?? 'unknown') . '; secret mode: ' . ($secretMode ?? 'unknown'),
            'fix' => 'Use both test keys for test mode or both live keys for production mode.',
        ];

        $checks[] = [
            'status' => $portalConfiguration === '' ? 'OK' : (str_starts_with($portalConfiguration, 'bpc_') ? 'OK' : 'WARN'),
            'label' => 'Stripe Billing Portal configuration ID',
            'details' => $portalConfiguration === '' ? 'Blank; Stripe default portal configuration will be used.' : $this->masked($portalConfiguration),
            'fix' => 'Leave blank or set to a bpc_... configuration ID.',
        ];

        $checks[] = [
            'status' => $this->paidPlansMissingPriceIds() > 0 ? 'CRIT' : 'OK',
            'label' => 'Paid plans have Stripe monthly Price IDs',
            'details' => (string) $this->paidPlansMissingPriceIds() . ' active paid plan(s) missing stripe_monthly_price_id.',
            'fix' => 'Add price_... monthly recurring IDs to every active paid plan.',
        ];

        $checks[] = [
            'status' => $this->paidPlansWithMalformedPriceIds() > 0 ? 'WARN' : 'OK',
            'label' => 'Paid plan Price-ID format',
            'details' => (string) $this->paidPlansWithMalformedPriceIds() . ' active paid plan(s) have non-price_ values.',
            'fix' => 'Use Stripe Price IDs beginning with price_. Do not use product IDs here.',
        ];

        $checks[] = [
            'status' => $this->freePlansWithPriceIds() > 0 ? 'WARN' : 'OK',
            'label' => 'Free plans avoid Stripe Price IDs',
            'details' => (string) $this->freePlansWithPriceIds() . ' active free plan(s) have Stripe price values set.',
            'fix' => 'Clear Stripe product/price fields on free plans.',
        ];

        $checks[] = [
            'status' => $this->webhookEventLogReady() ? 'OK' : 'WARN',
            'label' => 'Stripe webhook event log',
            'details' => $this->webhookEventLogReady() ? 'stripe_webhook_events table exists.' : 'stripe_webhook_events table missing; migration 0053 may not have run.',
            'fix' => 'Run php scripts/database/migrate.php so webhook idempotency can record events.',
        ];

        return $checks;
    }

    /** @param list<array{status:string}> $checks */
    private function summaryCards(array $checks): string
    {
        $critical = 0;
        $warning = 0;
        $ok = 0;

        foreach ($checks as $check) {
            if ($check['status'] === 'CRIT') {
                $critical += 1;
            } elseif ($check['status'] === 'WARN') {
                $warning += 1;
            } else {
                $ok += 1;
            }
        }

        return '<div class="admin-dashboard-cards">'
            . $this->summaryCard('Critical', $critical, 'Must fix before live billing')
            . $this->summaryCard('Warnings', $warning, 'Review before launch')
            . $this->summaryCard('Healthy', $ok, 'Configuration checks passing')
            . '</div>';
    }

    private function summaryCard(string $label, int $count, string $note): string
    {
        return '<article class="admin-stat-card"><strong>' . AdminLayout::escape((string) $count) . '</strong><span>' . AdminLayout::escape($label) . '</span><small>' . AdminLayout::escape($note) . '</small></article>';
    }

    /** @param list<array{status:string,label:string,details:string,fix:string}> $checks */
    private function checkRows(array $checks): string
    {
        $rows = '';
        foreach ($checks as $check) {
            $rows .= '<tr><td>' . $this->badge($check['status']) . '</td><td><strong>' . AdminLayout::escape($check['label']) . '</strong></td><td>' . AdminLayout::escape($check['details']) . '</td><td>' . AdminLayout::escape($check['fix']) . '</td></tr>';
        }

        return $rows;
    }

    private function planRows(): string
    {
        if (!$this->tableExists('plans')) {
            return '<tr><td colspan="5">plans table is missing.</td></tr>';
        }

        $hasProduct = $this->columnExists('plans', 'stripe_product_id');
        $hasPrice = $this->columnExists('plans', 'stripe_monthly_price_id');

        $productExpr = $hasProduct ? 'stripe_product_id' : 'NULL AS stripe_product_id';
        $priceExpr = $hasPrice ? 'stripe_monthly_price_id' : 'NULL AS stripe_monthly_price_id';

        try {
            $rows = $this->pdo->query(
                'SELECT id, slug, name, monthly_price_cents, is_active, ' . $productExpr . ', ' . $priceExpr . '
                   FROM plans
                  WHERE is_active = 1
                  ORDER BY monthly_price_cents ASC, id ASC'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return '<tr><td colspan="5">Plan query failed: ' . AdminLayout::escape($e->getMessage()) . '</td></tr>';
        }

        $html = '';
        foreach ($rows as $row) {
            $priceId = (string) ($row['stripe_monthly_price_id'] ?? '');
            $monthly = (int) ($row['monthly_price_cents'] ?? 0);
            $status = 'OK';
            if ($monthly > 0 && $priceId === '') {
                $status = 'CRIT';
            } elseif ($monthly > 0 && !str_starts_with($priceId, 'price_')) {
                $status = 'WARN';
            } elseif ($monthly === 0 && $priceId !== '') {
                $status = 'WARN';
            }

            $html .= '<tr>'
                . '<td><strong>' . AdminLayout::escape((string) $row['name']) . '</strong><br><small>' . AdminLayout::escape((string) $row['slug']) . '</small></td>'
                . '<td>' . AdminLayout::escape('$' . number_format($monthly / 100, 2)) . '</td>'
                . '<td><code>' . AdminLayout::escape($this->masked((string) ($row['stripe_product_id'] ?? ''))) . '</code></td>'
                . '<td><code>' . AdminLayout::escape($this->masked($priceId)) . '</code></td>'
                . '<td>' . $this->badge($status) . '</td>'
                . '</tr>';
        }

        return $html !== '' ? $html : '<tr><td colspan="5">No active plans found.</td></tr>';
    }

    private function paidPlansMissingPriceIds(): int
    {
        if (!$this->columnExists('plans', 'stripe_monthly_price_id')) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM plans WHERE is_active = 1 AND monthly_price_cents > 0 AND (stripe_monthly_price_id IS NULL OR stripe_monthly_price_id = '')");
    }

    private function paidPlansWithMalformedPriceIds(): int
    {
        if (!$this->columnExists('plans', 'stripe_monthly_price_id')) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM plans WHERE is_active = 1 AND monthly_price_cents > 0 AND stripe_monthly_price_id IS NOT NULL AND stripe_monthly_price_id <> '' AND stripe_monthly_price_id NOT LIKE 'price\\_%'");
    }

    private function freePlansWithPriceIds(): int
    {
        if (!$this->columnExists('plans', 'stripe_monthly_price_id')) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM plans WHERE is_active = 1 AND monthly_price_cents = 0 AND stripe_monthly_price_id IS NOT NULL AND stripe_monthly_price_id <> ''");
    }

    private function webhookEventLogReady(): bool
    {
        return $this->tableExists('stripe_webhook_events');
    }

    private function setting(string $key): string
    {
        if (!$this->tableExists('platform_settings')) {
            return '';
        }

        $stmt = $this->pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    private function stripeKeyMode(string $value, string $prefix): ?string
    {
        if (str_starts_with($value, $prefix . '_test_')) {
            return 'test';
        }
        if (str_starts_with($value, $prefix . '_live_')) {
            return 'live';
        }

        return null;
    }

    private function masked(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 12) {
            return substr($value, 0, 4) . '…';
        }

        return substr($value, 0, 8) . '…' . substr($value, -4);
    }

    private function scalarInt(string $sql): int
    {
        try {
            return (int) $this->pdo->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = :table
                AND column_name = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function badge(string $status): string
    {
        $label = strtoupper($status);
        $class = match ($label) {
            'OK' => 'admin-notice-success',
            'WARN' => 'admin-notice-warning',
            default => 'admin-notice-error',
        };

        return '<span class="admin-notice ' . $class . '">' . AdminLayout::escape($label) . '</span>';
    }
}

// End of file.
