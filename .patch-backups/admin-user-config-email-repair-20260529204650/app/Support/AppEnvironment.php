<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralizes runtime environment checks for safety-sensitive workflows.
 */
final class AppEnvironment
{
    public function __construct(
        private readonly string $environment,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(getenv('APP_ENV') ?: 'local');
    }

    public function name(): string
    {
        return $this->environment;
    }

    public function isLocal(): bool
    {
        return $this->environment === 'local';
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function requireProduction(string $operation): void
    {
        if (!$this->isProduction()) {
            throw new \RuntimeException(
                "{$operation} requires APP_ENV=production. Current APP_ENV={$this->environment}."
            );
        }
    }

    public function requireNonProduction(string $operation): void
    {
        if ($this->isProduction()) {
            throw new \RuntimeException(
                "{$operation} is blocked in production."
            );
        }
    }
}

// End of file.
