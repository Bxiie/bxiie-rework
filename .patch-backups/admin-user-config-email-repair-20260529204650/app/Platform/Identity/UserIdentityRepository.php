<?php

declare(strict_types=1);

namespace App\Platform\Identity;

use PDO;

/**
 * Handles authentication identity records linked to global users.
 */
final class UserIdentityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function addLocalPasswordIdentity(int $userId, string $email, bool $verified = false): int
    {
        return $this->create($userId, 'local_password', 'local', strtolower(trim($email)), $email, null, [], $verified);
    }

    public function addOauthIdentity(
        int $userId,
        string $provider,
        string $providerSubject,
        ?string $email,
        ?string $displayName = null,
        array $metadata = [],
        bool $verified = true
    ): int {
        return $this->create($userId, 'oauth_oidc', $provider, $providerSubject, $email, $displayName, $metadata, $verified);
    }

    public function findByProviderSubject(string $provider, string $providerSubject): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM user_identities
             WHERE provider = :provider AND provider_subject = :provider_subject
             LIMIT 1"
        );

        $stmt->execute([
            'provider' => $provider,
            'provider_subject' => $providerSubject,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function create(
        int $userId,
        string $identityType,
        string $provider,
        ?string $providerSubject,
        ?string $email,
        ?string $displayName,
        array $metadata,
        bool $verified
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_identities (
                user_id, identity_type, provider, provider_subject, email, display_name, metadata, verified_at
            ) VALUES (
                :user_id, :identity_type, :provider, :provider_subject, :email, :display_name, :metadata, :verified_at
            )"
        );

        $stmt->execute([
            'user_id' => $userId,
            'identity_type' => $identityType,
            'provider' => $provider,
            'provider_subject' => $providerSubject,
            'email' => $email ? strtolower(trim($email)) : null,
            'display_name' => $displayName,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'verified_at' => $verified ? date('Y-m-d H:i:s') : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}

// End of file.
