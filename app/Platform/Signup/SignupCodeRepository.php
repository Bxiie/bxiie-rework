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

    public function create(string $kind, string $label, ?string $recipientEmail, int $maxRedemptions, ?int $createdByUserId): array
    {
        if (!in_array($kind, ['one_time', 'blanket'], true)) {
            throw new RuntimeException('Signup code type must be one_time or blanket.');
        }
        $recipientEmail = $recipientEmail !== null && trim($recipientEmail) !== '' ? strtolower(trim($recipientEmail)) : null;
        if ($recipientEmail !== null && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Recipient email is invalid.');
        }
        $maxRedemptions = $kind === 'one_time' ? 1 : max(1, $maxRedemptions);
        $code = $this->generateCode($kind);

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_signup_codes (
                code, code_type, label, recipient_email, max_redemptions, redemption_count, status, created_by_user_id, created_at, updated_at
            ) VALUES (
                :code, :code_type, :label, :recipient_email, :max_redemptions, 0, 'active', :created_by_user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );
        $stmt->execute([
            'code' => $code,
            'code_type' => $kind,
            'label' => trim($label) !== '' ? trim($label) : ucfirst(str_replace('_', ' ', $kind)) . ' signup code',
            'recipient_email' => $recipientEmail,
            'max_redemptions' => $maxRedemptions,
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

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_signup_codes WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
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
        $prefix = $kind === 'one_time' ? 'AF1' : 'AFB';
        do {
            $code = $prefix . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(3)));
        } while ($this->findByCode($code) !== null);

        return $code;
    }
}

// End of file.
