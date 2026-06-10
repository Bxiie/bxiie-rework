<?php

declare(strict_types=1);

namespace App\Platform\Identity;

/**
 * Hashes and verifies local account passwords.
 */
final class PasswordHasher
{
    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new \RuntimeException('Unable to hash password.');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}

// End of file.
