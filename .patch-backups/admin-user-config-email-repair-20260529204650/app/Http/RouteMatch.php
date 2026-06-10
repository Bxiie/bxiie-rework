<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Represents a matched route and extracted path parameters.
 */
final class RouteMatch
{
    public function __construct(
        public readonly mixed $handler,
        public readonly array $parameters = [],
    ) {
    }
}

// End of file.
