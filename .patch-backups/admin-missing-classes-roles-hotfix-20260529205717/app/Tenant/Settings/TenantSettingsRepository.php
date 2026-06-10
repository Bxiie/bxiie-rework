<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Handles tenant-scoped settings persistence.
 */
final class TenantSettingsRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function get(TenantContext $tenant, string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT setting_value
             FROM tenant_settings
             WHERE tenant_id = :tenant_id
               AND setting_key = :setting_key
             LIMIT 1"
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'setting_key' => $key,
        ]);

        $row = $stmt->fetch();

        return $row ? (string) $row['setting_value'] : $default;
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
    }

    public function all(TenantContext $tenant): array
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
            $settings[(string) $row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }
}

// End of file.
