<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\Domains\ApacheVhostRenderer;

/**
 * Handles dry-run Apache vhost rendering jobs.
 */
final class RenderVhostJobHandler
{
    public function __construct(
        private readonly ApacheVhostRenderer $renderer,
    ) {
    }

    public function handle(array $payload): string
    {
        $hostname = (string) ($payload['hostname'] ?? '');
        $documentRoot = (string) ($payload['document_root'] ?? '/var/www/artsfolio/public');

        if ($hostname === '') {
            throw new \InvalidArgumentException('Missing hostname in render vhost payload.');
        }

        return $this->renderer->renderHttpVhost($hostname, $documentRoot);
    }
}

// End of file.
