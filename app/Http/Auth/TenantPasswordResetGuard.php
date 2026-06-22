<?php

declare(strict_types=1);

namespace App\Http\Auth;

use PDO;

/** Ensures tenant password-reset requests cannot target unrelated users. */
final class TenantPasswordResetGuard
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recipientExists(int $tenantId, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id
             FROM users u
             WHERE LOWER(u.email) = LOWER(:email)
               AND (
                   EXISTS (
                       SELECT 1
                       FROM tenant_memberships tm
                       WHERE tm.user_id = u.id
                         AND tm.tenant_id = :tenant_id
                         AND tm.status IN ('active', 'invited')
                   )
                   OR EXISTS (
                       SELECT 1
                       FROM tenant_users tu
                       WHERE tu.user_id = u.id
                         AND tu.tenant_id = :tenant_id
                         AND tu.status IN ('active', 'invited')
                   )
               )
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'email' => $email]);

        return (bool) $stmt->fetchColumn();
    }
}

// End of file.
