<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

/**
 * Immutable request-local view of every setting for one tenant.
 *
 * Controllers may perform as many lookups as needed without issuing
 * additional database queries after the snapshot has been loaded.
 */
final class TenantSettingsSnapshot
{
    /**
     * @param array<string, string|null> $values
     */
    public function __construct(
        private readonly array $values,
    ) {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (!array_key_exists($key, $this->values)) {
            return $default;
        }

        $value = $this->values[$key];

        return $value === null ? null : (string) $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function with(string $key, ?string $value): self
    {
        $values = $this->values;
        $values[$key] = $value;

        return new self($values);
    }
}

// End of file.
