<?php

declare(strict_types=1);

/**
 * Shared guard helpers for scripts/test.
 *
 * Mutating tests must never run against the production environment file.
 */
final class TestEnvironment
{
    public static function isProductionEnv(): bool
    {
        $envFile = (string) (getenv('ARTSFOLIO_ENV_FILE') ?: '');
        $normalized = str_replace('\\', '/', $envFile);

        return str_contains($normalized, '/etc/artsfolio/')
            || str_ends_with($normalized, '/artsfolio.env');
    }

    public static function skipIfProduction(string $scriptName): void
    {
        if (!self::isProductionEnv()) {
            return;
        }

        echo "Skipping {$scriptName} against production env.\n";
        exit(0);
    }
}

// End of file.
