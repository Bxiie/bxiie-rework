<?php

declare(strict_types=1);

/**
 * Manual verification script for flash message helper.
 */

use App\Support\Flash\FlashMessages;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

FlashMessages::success('Saved.');
$html = FlashMessages::consumeHtml();
$empty = FlashMessages::consumeHtml();

if (!str_contains($html, 'Saved.') || $empty !== '') {
    fwrite(STDERR, "Flash message behavior failed.\n");
    exit(1);
}

echo "Flash messages smoke test passed.\n";

// End of file.
