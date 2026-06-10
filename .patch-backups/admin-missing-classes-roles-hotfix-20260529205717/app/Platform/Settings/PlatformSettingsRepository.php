<?php

declare(strict_types=1);

namespace App\Platform\Settings;

use PDO;

/**
 * Stores platform-level settings that are not tenant/client-owned.
 */
final class PlatformSettingsRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT setting_value
             FROM platform_settings
             WHERE setting_key = :setting_key
             LIMIT 1"
        );

        $stmt->execute(['setting_key' => $key]);

        $row = $stmt->fetch();

        return $row ? (string) $row['setting_value'] : $default;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO platform_settings (
                setting_key,
                setting_value,
                updated_at
            ) VALUES (
                :setting_key,
                :setting_value,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            "SELECT setting_key, setting_value
             FROM platform_settings
             ORDER BY setting_key"
        );

        return $stmt->fetchAll();
    }
}

// End of file.
