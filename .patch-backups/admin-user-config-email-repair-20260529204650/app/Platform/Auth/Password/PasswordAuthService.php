<?php

declare(strict_types=1);

namespace App\Platform\Auth\Password;

use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Identity\UserIdentityRepository;
use App\Platform\Identity\UserRepository;

/**
 * Coordinates local email/password registration and login.
 */
final class PasswordAuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserIdentityRepository $identities,
        private readonly PasswordHasher $passwords,
        private readonly SessionRepository $sessions,
        private readonly SessionTokenService $tokens,
    ) {
    }

    public function register(
        string $email,
        string $password,
        ?string $displayName = null,
        bool $emailVerified = false,
    ): int {
        $existing = $this->users->findByEmail($email);

        if ($existing) {
            throw new \RuntimeException('A user with this email already exists.');
        }

        $userId = $this->users->create(
            email: $email,
            displayName: $displayName,
            passwordHash: $this->passwords->hash($password),
        );

        $this->identities->addLocalPasswordIdentity($userId, $email, $emailVerified);

        return $userId;
    }

    public function login(
        string $email,
        string $password,
        ?int $tenantId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $ttlSeconds = 1209600,
    ): array {
        $user = $this->users->findByEmail($email);

        if (!$user || empty($user['password_hash'])) {
            throw new \RuntimeException('Invalid email or password.');
        }

        if (!$this->passwords->verify($password, (string) $user['password_hash'])) {
            throw new \RuntimeException('Invalid email or password.');
        }

        $rawToken = $this->tokens->generateToken();
        $hash = $this->tokens->hashToken($rawToken);

        $sessionId = $this->sessions->create(
            sessionHash: $hash,
            userId: (int) $user['id'],
            tenantId: $tenantId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            ttlSeconds: $ttlSeconds,
        );

        return [
            'session_id' => $sessionId,
            'session_token' => $rawToken,
            'session_hash' => $hash,
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
        ];
    }
}

// End of file.
