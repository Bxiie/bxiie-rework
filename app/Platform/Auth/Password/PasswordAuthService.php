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

    /**
     * Revokes the active browser session represented by the raw cookie token.
     *
     * Logout must invalidate server-side state as well as expire cookies. This
     * prevents a stale cookie on another host/domain from re-opening admin pages.
     */
    public function logoutToken(?string $rawToken): void
    {
        if (!is_string($rawToken) || $rawToken === '') {
            return;
        }

        $this->sessions->revokeByHash($this->tokens->hashToken($rawToken));
    }


    /**
     * Revokes a browser session by the raw cookie token.
     */
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
    /**
     * Revokes a browser session by its raw cookie token.
     *
     * Logout must invalidate server-side session state, not merely expire the
     * browser cookie, because stale or sibling cookies can otherwise continue
     * to authenticate tenant admin requests.
     */
/**
     * Revoke a browser session by its raw cookie token.
     *
     * Session cookies store the raw token while user_sessions stores only the
     * SHA-256 hash. Logout must therefore hash the cookie value before marking
     * the backing server-side session revoked.
     */

    /**
     * Revoke a browser session from the raw cookie token.
     *
     * Logout links clear browser cookies, but the server-side session row must
     * also be revoked so back-button or cross-domain admin access cannot reuse
     * the old token.
     */

    /**
     * Revoke a browser session from the raw cookie token.
     *
     * Logout links clear browser cookies, but the server-side session row must
     * also be revoked so back-button or cross-domain admin access cannot reuse
     * the old token.
     */
    /**
     * Revoke a browser session from the raw cookie token.
     *
     * The password auth service does not own a PDO connection. It must use the
     * injected token service and session repository so logout works in both
     * tenant and platform runtime paths.
     */
    public function logoutSessionToken(string $rawToken): void
    {
        if ($rawToken === '') {
            return;
        }

        $this->sessions->revokeByHash($this->tokens->hashToken($rawToken));
    }


}
