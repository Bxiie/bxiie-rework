<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use RuntimeException;

/**
 * Encrypts provider access tokens before database persistence.
 *
 * ARTSFOLIO_SOCIAL_TOKEN_KEY must contain 32 random bytes encoded as base64.
 * Token values and plaintext keys must never be written to logs or audit data.
 */
final class SocialTokenCipher
{
    private const VERSION = 'v1';

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Refusing to encrypt an empty social access token.');
        }

        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return self::VERSION . ':' . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        [$version, $payload] = array_pad(explode(':', $encoded, 2), 2, '');
        if ($version !== self::VERSION || $payload === '') {
            throw new RuntimeException('Unsupported social token ciphertext.');
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid social token ciphertext.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt social access token.');
        }

        return $plaintext;
    }

    private function key(): string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('PHP sodium extension is required for social publishing token encryption.');
        }

        $configured = trim((string) (getenv('ARTSFOLIO_SOCIAL_TOKEN_KEY') ?: ''));
        $decoded = base64_decode($configured, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('ARTSFOLIO_SOCIAL_TOKEN_KEY must be base64-encoded 32-byte random data.');
        }

        return $decoded;
    }
}

// End of file.
