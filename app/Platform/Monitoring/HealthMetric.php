<?php

declare(strict_types=1);

namespace App\Platform\Monitoring;

/**
 * Immutable result for one operations-health measurement.
 */
final class HealthMetric
{
    public const OK = 'OK';
    public const WARN = 'WARN';
    public const CRIT = 'CRIT';
    public const INFO = 'INFO';

    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly string $expected,
        public readonly string $actual,
        public readonly string $detail = '',
    ) {
    }

    public function isTrouble(): bool
    {
        return in_array($this->status, [self::WARN, self::CRIT], true);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'detail' => $this->detail,
        ];
    }

    public function toText(): string
    {
        $line = sprintf(
            '[%s] %s | expected: %s | actual: %s',
            $this->status,
            $this->name,
            $this->expected,
            $this->actual,
        );

        return $this->detail !== '' ? $line . ' | ' . $this->detail : $line;
    }
}

// End of file.
