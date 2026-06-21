<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\Analytics\AnalyticsRollupService;

final class AnalyticsRollupJobHandler
{
    public function __construct(private readonly AnalyticsRollupService $service) {}
    public function handle(array $payload): string
    {
        $result=$this->service->rebuildRecent((int)($payload['days'] ?? 3));
        return sprintf('Analytics rollups rebuilt: %d hourly rows, %d daily rows, %d days.', $result['hourly_rows'],$result['daily_rows'],$result['days']);
    }
}

// End of file.
