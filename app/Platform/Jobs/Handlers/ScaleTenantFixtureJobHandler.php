<?php

/**
 * Background job handler for synthetic scale tenant fixtures.
 */

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\ScaleTesting\ScaleTenantFixtureService;
use RuntimeException;

/**
 * Runs long scale fixture operations outside the browser request path.
 */
final class ScaleTenantFixtureJobHandler
{
    public function __construct(
        private readonly ScaleTenantFixtureService $fixtures,
    ) {
    }

    /**
     * Executes a scale fixture job and returns a compact worker log message.
     *
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload): string
    {
        $jobType = (string) ($payload['action'] ?? 'seed');
        if ($jobType === 'cleanup') {
            $summary = $this->fixtures->cleanup();
            return 'Scale fixture cleanup complete: ' . json_encode($summary, JSON_THROW_ON_ERROR);
        }

        if (!in_array($jobType, ['seed', 'reset'], true)) {
            throw new RuntimeException('Unsupported scale fixture action: ' . $jobType);
        }

        $tenantCount = $this->positiveInt($payload['tenants'] ?? 1000, 1000);
        $artworksPerTenant = $this->boundedInt($payload['artworks_per_tenant'] ?? 50, 0, 500, 50);
        $eventsPerTenant = $this->boundedInt($payload['events_per_tenant'] ?? 200, 0, 5000, 200);

        if ($jobType === 'reset') {
            $this->fixtures->cleanup();
        }

        $summary = $this->fixtures->seed($tenantCount, $artworksPerTenant, $eventsPerTenant);
        return 'Scale fixture seed complete: ' . json_encode($summary, JSON_THROW_ON_ERROR);
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $string = trim((string) $value);
        if (!preg_match('/^\d+$/', $string)) {
            return $default;
        }

        return max(1, (int) $string);
    }

    private function boundedInt(mixed $value, int $min, int $max, int $default): int
    {
        $string = trim((string) $value);
        if (!preg_match('/^\d+$/', $string)) {
            return $default;
        }

        return max($min, min($max, (int) $string));
    }
}

// End of file.
