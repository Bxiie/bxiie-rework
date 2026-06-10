<?php

declare(strict_types=1);

namespace App\Platform\Auth\OAuth;

use PDO;

/**
 * Reads active OAuth2 bearer access tokens.
 */
final class BearerTokenRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                oat.*,
                u.email,
                u.display_name
             FROM oauth_access_tokens oat
             JOIN users u ON u.id = oat.user_id
             WHERE oat.token_hash = :token_hash
               AND oat.revoked_at IS NULL
               AND oat.expires_at > CURRENT_TIMESTAMP
             LIMIT 1"
        );

        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

// End of file.
