<?php

declare(strict_types=1);

namespace App\Platform\Auth\Email;

use App\Platform\Identity\UserRepository;
use PDO;

/**
 * Coordinates local account email verification token creation and consumption.
 */
final class EmailVerificationService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepository $users,
        private readonly EmailVerificationTokenRepository $tokens,
    ) {
    }

    public function createVerificationTokenForEmail(string $email, ?int $tenantId = null): ?array
    {
        $user = $this->users->findByEmail($email);

        if (!$user) {
            return null;
        }

        $rawToken = $this->generateToken();
        $tokenHash = $this->hashToken($rawToken);

        $tokenId = $this->tokens->create(
            userId: (int) $user['id'],
            email: (string) $user['email'],
            tokenHash: $tokenHash,
            tenantId: $tenantId,
        );

        return [
            'token_id' => $tokenId,
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'tenant_id' => $tenantId,
            'verification_token' => $rawToken,
            'token_hash' => $tokenHash,
        ];
    }

    public function verifyEmail(string $rawToken): array
    {
        $tokenHash = $this->hashToken($rawToken);
        $token = $this->tokens->findActiveByHash($tokenHash);

        if (!$token) {
            throw new \RuntimeException('Email verification token is invalid, expired, or already consumed.');
        }

        $this->pdo->beginTransaction();

        try {
            $identityStmt = $this->pdo->prepare(
                "UPDATE user_identities
                 SET verified_at = COALESCE(verified_at, CURRENT_TIMESTAMP),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE user_id = :user_id
                   AND email = :email"
            );

            $identityStmt->execute([
                'user_id' => (int) $token['user_id'],
                'email' => strtolower(trim((string) $token['email'])),
            ]);

            // The primary users table is the source used by signup and admin
            // screens. Keep it synchronized with the identity record.
            $userStmt = $this->pdo->prepare(
                "UPDATE users
                 SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :user_id
                   AND LOWER(email) = :email"
            );
            $userStmt->execute([
                'user_id' => (int) $token['user_id'],
                'email' => strtolower(trim((string) $token['email'])),
            ]);

            $this->tokens->consume((int) $token['id']);

            $this->pdo->commit();

            return [
                'user_id' => (int) $token['user_id'],
                'email' => (string) $token['email'],
                'tenant_id' => isset($token['tenant_id']) ? (int) $token['tenant_id'] : null,
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

// End of file.
