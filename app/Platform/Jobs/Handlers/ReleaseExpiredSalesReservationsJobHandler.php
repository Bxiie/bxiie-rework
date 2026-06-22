<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Tenant\Sales\SalesRepository;

/** Releases abandoned checkout inventory reservations. */
final class ReleaseExpiredSalesReservationsJobHandler
{
    public function __construct(private readonly SalesRepository $sales) {}

    public function handle(array $payload): string
    {
        $released = $this->sales->releaseExpiredReservations();
        return sprintf('Released %d expired sales inventory reservation(s).', $released);
    }
}

// End of file.
