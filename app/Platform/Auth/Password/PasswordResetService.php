<?php

declare(strict_types=1);

namespace App\Platform\Auth\Password;

use App\Platform\Identity\PasswordHasher;
use App\Platform\Identity\UserRepository;
use PDO;

/**
 * Coordinates local password reset token creation and password updates.
 */
final class PasswordResetService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords,
        private readonly PasswordResetTokenRepository $tokens,
    ) {
    }

    public function createResetTokenForEmail(string $email): ?array
    {
        $user = $this->users->findByEmail($email);

        if (!$user) {
            return null;
        }

        $rawToken = $this->generateToken();
        $tokenHash = $this->hashToken($rawToken);

        $tokenId = $this->tokens->create(
            userId: (int) $user['id'],
            tokenHash: $tokenHash,
        );

        return [
            'token_id' => $tokenId,
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'reset_token' => $rawToken,
            'token_hash' => $tokenHash,
        ];
    }

    public function resetPassword(string $rawToken, string $newPassword): int
    {
        $tokenHash = $this->hashToken($rawToken);
        $token = $this->tokens->findActiveByHash($tokenHash);

        if (!$token) {
            throw new \RuntimeException('Password reset token is invalid, expired, or already consumed.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE users
                 SET password_hash = :password_hash,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $stmt->execute([
                'password_hash' => $this->passwords->hash($newPassword),
                'id' => (int) $token['user_id'],
            ]);

            if ($stmt->rowCount() < 1) {
                throw new \RuntimeException('Password reset token does not belong to an active user.');
            }

            $this->tokens->consume((int) $token['id']);

            $this->pdo->commit();

            return (int) $token['user_id'];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Create a reset token only when the email belongs to an active user of the
     * tenant that received the reset request.
     */
    /**
     * Reset a password from a tenant reset form only when the active token is
     * tied to a user who still belongs to the same tenant.
     */
    /**
     * Determine whether a user is an active member of a tenant.
     */
    /**
     * Create a reset token only when the email belongs to an active user of the
     * tenant that received the reset request.
     */
    /**
     * Reset a password from a tenant reset form only when the token belongs to
     * a user who is still active on that tenant.
     */
    /**
     * Check active tenant membership before issuing or consuming tenant reset tokens.
     */
    /**
     * Create a reset token only when the email belongs to an active user of the
     * tenant that received the reset request.
     */
    public function createResetTokenForTenantEmail(string $email, ?int $tenantId): ?array
    {
        if ($tenantId === null || $tenantId < 1) {
            return null;
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !$this->userBelongsToTenant((int) $user['id'], $tenantId)) {
            return null;
        }

        $rawToken = $this->generateToken();
        $tokenHash = $this->hashToken($rawToken);

        $tokenId = $this->tokens->create(
            userId: (int) $user['id'],
            tokenHash: $tokenHash,
        );

        return [
            'token_id' => $tokenId,
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'reset_token' => $rawToken,
            'token_hash' => $tokenHash,
        ];
    }

    /**
     * Reset a password from a tenant reset form only when the token belongs to
     * a user who is still active on that tenant.
     */
    /**
     * Create a password-setting token for an invited or active tenant member.
     */
    public function createInvitationTokenForTenantEmail(string $email, ?int $tenantId): ?array
    {
        if ($tenantId === null || $tenantId < 1) {
            return null;
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !$this->userBelongsToTenant((int) $user['id'], $tenantId, ['active', 'invited'])) {
            return null;
        }

        $rawToken = $this->generateToken();
        $tokenHash = $this->hashToken($rawToken);
        $tokenId = $this->tokens->create(
            userId: (int) $user['id'],
            tokenHash: $tokenHash,
        );

        return [
            'token_id' => $tokenId,
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'reset_token' => $rawToken,
            'token_hash' => $tokenHash,
        ];
    }
    public function resetPasswordForTenant(string $rawToken, string $newPassword, ?int $tenantId): int
    {
        if ($tenantId === null || $tenantId < 1) {
            throw new \RuntimeException('Password reset token is invalid for this tenant.');
        }

        $tokenHash = $this->hashToken($rawToken);
        $token = $this->tokens->findActiveByHash($tokenHash);

        if (!$token || !$this->userBelongsToTenant((int) $token['user_id'], $tenantId, ['active', 'invited'])) {
            throw new \RuntimeException('Password reset token is invalid for this tenant.');
        }

        $userId = $this->resetPassword($rawToken, $newPassword);

        $stmt = $this->pdo->prepare(
            "UPDATE tenant_memberships
             SET status = 'active',
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id
               AND user_id = :user_id
               AND status = 'invited'"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        return $userId;
    }

    /**
     * Check active tenant membership before issuing or consuming tenant reset tokens.
     */
    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Check active tenant membership before issuing or consuming tenant reset tokens.
     */
    /**
     * @param list<string> $statuses
     */
    private function userBelongsToTenant(
        int $userId,
        int $tenantId,
        array $statuses = ['active'],
    ): bool {
        $statuses = array_values(array_filter(
            $statuses,
            static fn (string $status): bool => in_array($status, ['active', 'invited'], true),
        ));
        if ($statuses === []) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM tenant_memberships
             WHERE tenant_id = ?
               AND user_id = ?
               AND status IN ({$placeholders})
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $userId, ...$statuses]);

        return (bool) $stmt->fetchColumn();
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

// End of file.
