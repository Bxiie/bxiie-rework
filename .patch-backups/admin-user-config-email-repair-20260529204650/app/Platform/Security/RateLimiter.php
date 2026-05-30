<?php

declare(strict_types=1);

namespace App\Platform\Security;

use PDO;

/**
 * Simple fixed-window database-backed rate limiter.
 */
final class RateLimiter
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function allow(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $windowStart = $this->windowStart($windowSeconds);

        $stmt = $this->pdo->prepare(
            "INSERT INTO rate_limits (
                rate_key,
                window_starts_at,
                attempts,
                updated_at
            ) VALUES (
                :rate_key,
                :window_starts_at,
                1,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                attempts = attempts + 1,
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'rate_key' => $key,
            'window_starts_at' => $windowStart,
        ]);

        $check = $this->pdo->prepare(
            "SELECT attempts
             FROM rate_limits
             WHERE rate_key = :rate_key
               AND window_starts_at = :window_starts_at
             LIMIT 1"
        );

        $check->execute([
            'rate_key' => $key,
            'window_starts_at' => $windowStart,
        ]);

        $row = $check->fetch();

        return $row && (int) $row['attempts'] <= $maxAttempts;
    }

    public function attempts(string $key, int $windowSeconds): int
    {
        $windowStart = $this->windowStart($windowSeconds);

        $stmt = $this->pdo->prepare(
            "SELECT attempts
             FROM rate_limits
             WHERE rate_key = :rate_key
               AND window_starts_at = :window_starts_at
             LIMIT 1"
        );

        $stmt->execute([
            'rate_key' => $key,
            'window_starts_at' => $windowStart,
        ]);

        $row = $stmt->fetch();

        return $row ? (int) $row['attempts'] : 0;
    }

    private function windowStart(int $windowSeconds): string
    {
        $now = time();
        $start = $now - ($now % $windowSeconds);

        return gmdate('Y-m-d H:i:s', $start);
    }
}

// End of file.
