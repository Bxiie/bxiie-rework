<?php

declare(strict_types=1);

namespace App\Platform\Identity;

use App\Support\Uuid;
use PDO;

/**
 * Handles global user persistence.
 */
final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $email, ?string $displayName = null, ?string $passwordHash = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (uuid, email, password_hash, display_name)
             VALUES (:uuid, :email, :password_hash, :display_name)"
        );

        $stmt->execute([
            'uuid' => Uuid::v4(),
            'email' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

// End of file.
