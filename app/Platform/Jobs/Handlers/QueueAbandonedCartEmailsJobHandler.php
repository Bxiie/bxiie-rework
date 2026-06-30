<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Tenant\Sales\AbandonedCartEmailQueueService;

/** Runs the recurring abandoned-cart reminder queue pass. */
final class QueueAbandonedCartEmailsJobHandler
{
    public function __construct(private readonly AbandonedCartEmailQueueService $service)
    {
    }

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): string
    {
        $limit = max(1, min(1000, (int) ($payload['limit_per_stage'] ?? 200)));
        $result = $this->service->queueDue($limit);

        return sprintf(
            'Queued abandoned-cart reminders: 1d=%d, 3d=%d, 7d=%d, total=%d.',
            $result['queued_1d'],
            $result['queued_3d'],
            $result['queued_7d'],
            $result['total']
        );
    }
}

// End of file.
