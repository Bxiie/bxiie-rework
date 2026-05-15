<?php

declare(strict_types=1);

/**
 * Manual verification script for Apache vhost rendering.
 */

use App\Platform\Domains\ApacheVhostRenderer;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$hostname = $argv[1] ?? 'example-artist.com';
$documentRoot = $argv[2] ?? '/var/www/artsfolio/public';

$renderer = new ApacheVhostRenderer();

echo $renderer->renderHttpVhost(
    hostname: $hostname,
    documentRoot: $documentRoot,
);

// End of file.
