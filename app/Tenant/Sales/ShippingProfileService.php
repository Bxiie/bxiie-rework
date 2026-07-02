<?php
/**
 * Tenant shipping profile helper.
 *
 * Shipping profiles group similar items so checkout charges one sensible
 * shipping amount per profile instead of one base shipping fee per artwork.
 */

declare(strict_types=1);

namespace App\Tenant\Sales;

use PDO;

final class ShippingProfileService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function profiles(int $tenantId): array
    {
        $this->ensureDefaultProfiles($tenantId);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_shipping_profiles WHERE tenant_id = :tenant_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function profile(int $tenantId, ?int $profileId): ?array
    {
        $this->ensureDefaultProfiles($tenantId);
        if ($profileId !== null && $profileId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tenant_shipping_profiles WHERE tenant_id = :tenant_id AND id = :id LIMIT 1'
            );
            $stmt->execute(['tenant_id' => $tenantId, 'id' => $profileId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_shipping_profiles WHERE tenant_id = :tenant_id AND is_default = 1 ORDER BY sort_order ASC, id ASC LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_shipping_profiles WHERE tenant_id = :tenant_id ORDER BY sort_order ASC, id ASC LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function ensureDefaultProfiles(int $tenantId): void
    {
        $defaults = [
            ['Small flat items', 'small_flat', 'flat_profile', 500, 0, 500, 1, 'Small flat items ship together for one flat charge.', 1, 100],
            ['Small merchandise', 'small_merch', 'capped', 600, 200, 1400, 1, 'Small merchandise shipping is capped for combined orders.', 0, 110],
            ['Free shipping', 'free_shipping', 'free', 0, 0, 0, 1, 'Shipping is included.', 0, 120],
            ['Large artwork / quoted shipping', 'large_quote', 'quote', 0, 0, null, 0, 'Shipping is quoted by the artist before checkout.', 0, 130],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_shipping_profiles
                (tenant_id, name, code, mode, base_shipping_cents, additional_item_cents, max_shipping_cents, allow_checkout, buyer_label, is_default, sort_order)
             VALUES
                (:tenant_id, :name, :code, :mode, :base, :additional, :max, :allow_checkout, :buyer_label, :is_default, :sort_order)
             ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($defaults as $profile) {
            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $profile[0],
                'code' => $profile[1],
                'mode' => $profile[2],
                'base' => $profile[3],
                'additional' => $profile[4],
                'max' => $profile[5],
                'allow_checkout' => $profile[6],
                'buyer_label' => $profile[7],
                'is_default' => $profile[8],
                'sort_order' => $profile[9],
            ]);
        }
    }
}

// End of file.
