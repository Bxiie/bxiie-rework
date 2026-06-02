<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Tenancy\TenantContext;
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

        $planKey = $this->setting($tenant, 'billing_plan', 'studio');
        $plans = $this->plans();
        $plan = $plans[$planKey] ?? $plans['studio'];
        $usage = $this->usage($tenant);

        $rows = '';
        foreach ($plan['limits'] as $key => $limit) {
            $used = $usage[$key] ?? 0;
            $rows .= '<tr><td>' . $this->e($this->label($key)) . '</td><td>' . $this->e((string) $limit) . '</td><td>' . $this->e((string) $used) . '</td><td>' . $this->e($this->status((float) $used, (float) $limit)) . '</td></tr>';
        }

        $directory = $this->truthy($this->setting($tenant, 'platform_directory_opt_in', '0')) ? 'Enabled' : 'Off';
        $analytics = $this->countRows('analytics_events', $tenant);
        $rows .= '<tr><td>Directory/discovery listing</td><td>Opt-in</td><td>' . $directory . '</td><td>' . ($directory === 'Enabled' ? 'Visible when public content exists' : 'Off') . '</td></tr>';
        $rows .= '<tr><td>Analytics events</td><td>' . ($planKey === 'starter' ? 'Basic' : 'Advanced') . '</td><td>' . $analytics . '</td><td>' . ($analytics > 0 ? 'Receiving events' : 'No events yet') . '</td></tr>';
        $commission = $this->platformCommissionPercent();

        $body = <<<HTML
<section class="admin-billing-summary">
  <div class="admin-panel">
    <p class="admin-muted">Current pricing tier</p>
    <h2>{$this->e($plan['name'])}</h2>
    <p class="billing-price">{$this->e($plan['price'])}</p>
    <p>{$this->e($plan['summary'])}</p>
    <p><strong>Platform sales commission:</strong> {$this->e($commission)}</p>
    <p><a class="admin-button" href="/pricing">Review public pricing</a></p>
  </div>
  <div class="admin-panel">
    <p class="admin-muted">Billing status</p>
    <h2>Manual / pending</h2>
    <p>Billing-provider integration is not enabled yet. This page exposes the feature map and tenant usage so pricing gates can be added cleanly.</p>
  </div>
</section>

<section class="admin-panel admin-panel-wide">
  <h2>Feature usage by selected pricing tier</h2>
  <p class="admin-muted">Usage is calculated from tenant-scoped platform tables when available.</p>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Feature</th><th>Included</th><th>Used</th><th>Status</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table></div>
</section>
HTML;

        return Response::html(AdminLayout::render('Billing', $body));
    }

    private function plans(): array
    {
        if ($this->tableExists('plans')) {
            try {
                $columns = $this->planColumns();
                $select = 'slug, name, monthly_price_cents, custom_domain_included'
                    . ($columns['description'] ? ', description' : ', NULL AS description')
                    . ($columns['allowed_artworks'] ? ', allowed_artworks' : ', NULL AS allowed_artworks')
                    . ($columns['allowed_email_addresses'] ? ', allowed_email_addresses' : ', NULL AS allowed_email_addresses')
                    . ($columns['display_order'] ? ', display_order' : ', 100 AS display_order');
                $stmt = $this->pdo->query("SELECT {$select} FROM plans WHERE is_active = 1 ORDER BY display_order ASC, monthly_price_cents ASC, id ASC");
                $plans = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $slug = (string) $row['slug'];
                    $plans[$slug] = [
                        'name' => (string) $row['name'],
                        'price' => ((int) $row['monthly_price_cents']) === 0 ? '$0' : '$' . number_format(((int) $row['monthly_price_cents']) / 100, 2) . ' / month',
                        'summary' => (string) ($row['description'] ?? 'ArtsFolio artist portfolio plan.'),
                        'limits' => [
                            'artworks' => (int) ($row['allowed_artworks'] ?? 0),
                            'storage_gb' => 0,
                            'email_signups' => (int) ($row['allowed_email_addresses'] ?? 0),
                            'contact_messages' => 0,
                            'custom_domains' => (int) $row['custom_domain_included'],
                            'admin_users' => 0,
                        ],
                    ];
                }
                if ($plans !== []) {
                    return $plans;
                }
            } catch (Throwable) {
                // Fall back to static plan labels when a migration is mid-flight.
            }
        }

        return [
            'free' => ['name' => 'Free', 'price' => '$0', 'summary' => 'For launching a compact artist portfolio.', 'limits' => ['artworks' => 25, 'storage_gb' => 0, 'email_signups' => 100, 'contact_messages' => 0, 'custom_domains' => 0, 'admin_users' => 1]],
            'studio' => ['name' => 'Studio', 'price' => '$12.00 / month', 'summary' => 'For active artists who need analytics, subscribers, and sections.', 'limits' => ['artworks' => 250, 'storage_gb' => 0, 'email_signups' => 2500, 'contact_messages' => 0, 'custom_domains' => 0, 'admin_users' => 3]],
            'pro' => ['name' => 'Professional', 'price' => '$24.00 / month', 'summary' => 'For artists who need a custom domain and advanced branding.', 'limits' => ['artworks' => 1000, 'storage_gb' => 0, 'email_signups' => 10000, 'contact_messages' => 0, 'custom_domains' => 1, 'admin_users' => 10]],
        ];
    }


    private function platformCommissionPercent(): string
    {
        if (!$this->tableExists('platform_settings')) {
            return 'Configured by ArtsFolio';
        }
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_sales_commission_basis_points' LIMIT 1");
            $stmt->execute();
            $basisPoints = max(0, min(10000, (int) ($stmt->fetchColumn() ?: 500)));
            return number_format($basisPoints / 100, 2) . '% of platform-processed sales';
        } catch (Throwable) {
            return 'Configured by ArtsFolio';
        }
    }

    private function planColumns(): array
    {
        $columns = ['description' => false, 'allowed_artworks' => false, 'allowed_email_addresses' => false, 'display_order' => false];
        try {
            $stmt = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute(['table' => 'plans']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
                if (array_key_exists((string) $column, $columns)) {
                    $columns[(string) $column] = true;
                }
            }
        } catch (Throwable) {
            return $columns;
        }
        return $columns;
    }

    private function usage(TenantContext $tenant): array
    {
        return [
            'artworks' => $this->countRows('artworks', $tenant),
            'storage_gb' => $this->storageGb($tenant),
            'email_signups' => $this->countRows('email_signups', $tenant),
            'contact_messages' => $this->countRows('contact_messages', $tenant),
            'custom_domains' => $this->countRows('tenant_domains', $tenant),
            'admin_users' => $this->countMemberships($tenant),
        ];
    }

    private function label(string $key): string
    {
        return ['artworks'=>'Artwork records','storage_gb'=>'Media storage GB','email_signups'=>'Email subscribers','contact_messages'=>'Contact messages','custom_domains'=>'Custom domains','admin_users'=>'Admin users'][$key] ?? $key;
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
                try {
                    $stmt = $this->pdo->prepare("SELECT COALESCE(SUM({$column}),0) FROM {$table} WHERE tenant_id = :tenant_id");
                    $stmt->execute(['tenant_id' => $tenant->tenantId]);
                    return round(((float) $stmt->fetchColumn()) / 1024 / 1024 / 1024, 2);
                } catch (Throwable) {
                }
            }
        }

        return 0.0;
    }

    private function setting(TenantContext $tenant, string $key, string $default = ''): string
    {
        foreach (['tenant_settings', 'settings'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            try {
                $stmt = $this->pdo->prepare("SELECT setting_value FROM {$table} WHERE tenant_id = :tenant_id AND setting_key = :setting_key LIMIT 1");
                $stmt->execute(['tenant_id' => $tenant->tenantId, 'setting_key' => $key]);
                $value = $stmt->fetchColumn();
                return $value === false ? $default : (string) $value;
            } catch (Throwable) {
            }
        }

        return $default;
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE " . $this->pdo->quote($table));
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
        return in_array(strtolower(trim($value)), ['1','true','yes','on'], true);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
