<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Handles tenant-scoped settings persistence.
 *
 * A tenant's settings are bulk-loaded once per repository lifetime. In the
 * normal PHP request lifecycle this means one query per tenant per request,
 * even when a controller performs many setting lookups.
 */
final class TenantSettingsRepository
{
    /** @var array<int, TenantSettingsSnapshot> */
    private array $snapshots = [];

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function get(TenantContext $tenant, string $key, ?string $default = null): ?string
    {
        return $this->snapshot($tenant)->get($key, $default);
    }

    public function set(TenantContext $tenant, string $key, ?string $value): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_settings (
                tenant_id,
                setting_key,
                setting_value,
                updated_at
            ) VALUES (
                :tenant_id,
                :setting_key,
                :setting_value,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'setting_key' => $key,
            'setting_value' => $value,
        ]);

        // Keep an already-loaded request snapshot coherent after a save.
        if (isset($this->snapshots[$tenant->tenantId])) {
            $this->snapshots[$tenant->tenantId] = $this->snapshots[$tenant->tenantId]->with($key, $value);
        }
    }

    /**
     * Returns all tenant settings while preserving the existing array API.
     *
     * @return array<string, string|null>
     */
    public function all(TenantContext $tenant): array
    {
        return $this->snapshot($tenant)->all();
    }

    public function snapshot(TenantContext $tenant): TenantSettingsSnapshot
    {
        if (!isset($this->snapshots[$tenant->tenantId])) {
            $this->snapshots[$tenant->tenantId] = $this->loadSnapshot($tenant);
        }

        return $this->snapshots[$tenant->tenantId];
    }

    /**
     * Invalidates one request-local snapshot.
     *
     * This is primarily useful after direct SQL changes that bypass set().
     */
    public function invalidate(TenantContext $tenant): void
    {
        unset($this->snapshots[$tenant->tenantId]);
    }

    private function loadSnapshot(TenantContext $tenant): TenantSettingsSnapshot
    {
        $stmt = $this->pdo->prepare(
            "SELECT setting_key, setting_value
             FROM tenant_settings
             WHERE tenant_id = :tenant_id
             ORDER BY setting_key"
        );

        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $settings = [];

        foreach ($stmt->fetchAll() as $row) {
            $settings[(string) $row['setting_key']] = $row['setting_value'] === null
                ? null
                : (string) $row['setting_value'];
        }

        return new TenantSettingsSnapshot($settings);
    }
}

// End of file.
