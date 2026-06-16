<?php

declare(strict_types=1);

namespace App\Platform\Signup;

use PDO;
use RuntimeException;

/**
 * Manages platform-issued signup passcodes for tenant creation gating.
 */
final class SignupCodeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $kind, string $label, ?string $recipientEmail, int $maxRedemptions, ?int $createdByUserId, int $freeAccessMonths = 0): array
    {
        if (!in_array($kind, ['one_time', 'blanket', 'free_months'], true)) {
            throw new RuntimeException('Signup code type must be one_time, blanket, or free_months.');
        }
        $recipientEmail = $recipientEmail !== null && trim($recipientEmail) !== '' ? strtolower(trim($recipientEmail)) : null;
        if ($recipientEmail !== null && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Recipient email is invalid.');
        }
        $maxRedemptions = $kind === 'one_time' ? 1 : max(1, $maxRedemptions);
        $freeAccessMonths = $kind === 'free_months' ? max(1, min(60, $freeAccessMonths)) : 0;
        $code = $this->generateCode($kind);

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_signup_codes (
                code, code_type, label, recipient_email, max_redemptions, free_access_months, redemption_count, status, created_by_user_id, created_at, updated_at
            ) VALUES (
                :code, :code_type, :label, :recipient_email, :max_redemptions, :free_access_months, 0, 'active', :created_by_user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );
        $stmt->execute([
            'code' => $code,
            'code_type' => $kind,
            'label' => trim($label) !== '' ? trim($label) : ucfirst(str_replace('_', ' ', $kind)) . ' signup code',
            'recipient_email' => $recipientEmail,
            'max_redemptions' => $maxRedemptions,
            'free_access_months' => $freeAccessMonths,
            'created_by_user_id' => $createdByUserId,
        ]);

        return $this->findByCode($code) ?? ['code' => $code];
    }

    public function listRecent(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, t.slug AS redeemed_tenant_slug, t.name AS redeemed_tenant_name
             FROM tenant_signup_codes c
             LEFT JOIN tenants t ON t.id = c.redeemed_tenant_id
             ORDER BY c.id DESC
             LIMIT :limit_count"
        );
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Lists plans that may be selected during free-month signup redemption.
     */
    public function listActivePlans(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, slug, name, monthly_price_cents
             FROM plans
             WHERE is_active = 1
             ORDER BY monthly_price_cents ASC, id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_signup_codes WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Validates that a signup code can start the tenant signup flow.
     *
     * Recipient-email restrictions are enforced later during final submission,
     * after the prospective tenant has entered an email address.
     */
    public function validateForEntry(string $code): array
    {
        $row = $this->findByCode($code);
        if (!$row) {
            throw new RuntimeException('Signup code is invalid.');
        }
        if ((string) ($row['status'] ?? '') !== 'active') {
            throw new RuntimeException('Signup code is not active.');
        }
        if ((int) ($row['redemption_count'] ?? 0) >= (int) ($row['max_redemptions'] ?? 1)) {
            throw new RuntimeException('Signup code has already been fully redeemed.');
        }

        return $row;
    }

    public function validateForSignup(string $code, string $email): array
    {
        $row = $this->findByCode($code);
        if (!$row) {
            throw new RuntimeException('Signup code is invalid.');
        }
        if ((string) ($row['status'] ?? '') !== 'active') {
            throw new RuntimeException('Signup code is not active.');
        }
        $recipient = strtolower(trim((string) ($row['recipient_email'] ?? '')));
        if ($recipient !== '' && $recipient !== strtolower(trim($email))) {
            throw new RuntimeException('Signup code is limited to a different recipient email.');
        }
        if ((int) ($row['redemption_count'] ?? 0) >= (int) ($row['max_redemptions'] ?? 1)) {
            throw new RuntimeException('Signup code has already been fully redeemed.');
        }

        return $row;
    }

    /**
     * Revokes any signup code type so it cannot be used again.
     */
    public function revoke(int $codeId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenant_signup_codes
             SET status = 'revoked', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status <> 'revoked'"
        );
        $stmt->execute(['id' => $codeId]);
    }

    /**
     * Validates a free-access code for use by an existing tenant from billing.
     */
    public function validateFreeAccessForExistingTenant(string $code, string $email): array
    {
        $row = $this->validateForSignup($code, $email);
        if ((string) ($row['code_type'] ?? '') !== 'free_months') {
            throw new RuntimeException('Only free access signup codes can be applied from tenant billing.');
        }
        if ((int) ($row['free_access_months'] ?? 0) < 1) {
            throw new RuntimeException('Free access code does not include a free-month grant.');
        }

        return $row;
    }

    public function markRedeemed(int $codeId, int $tenantId, string $email): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenant_signup_codes
             SET redemption_count = redemption_count + 1,
                 redeemed_tenant_id = COALESCE(redeemed_tenant_id, :tenant_id),
                 redeemed_by_email = :email,
                 redeemed_at = COALESCE(redeemed_at, CURRENT_TIMESTAMP),
                 status = CASE WHEN redemption_count + 1 >= max_redemptions THEN 'redeemed' ELSE status END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $codeId,
            'tenant_id' => $tenantId,
            'email' => strtolower(trim($email)),
        ]);
    }

    private function generateCode(string $kind): string
    {
        $prefix = match ($kind) {
            'one_time' => 'AF1',
            'free_months' => 'AFF',
            default => 'AFB',
        };
        do {
            $code = $prefix . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(3)));
        } while ($this->findByCode($code) !== null);

        return $code;
    }
}

// End of file.
