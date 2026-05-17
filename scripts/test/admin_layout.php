<?php

declare(strict_types=1);

/**
 * Manual verification script for shared admin layout.
 */

use App\Http\View\AdminLayout;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$html = AdminLayout::render(
    title: 'Admin Layout Test',
    body: '<p>Layout body</p>',
    nav: [
        '/admin' => 'Admin',
        '/admin/audit-log' => 'Audit Log',
    ],
);

if (!str_contains($html, '/assets/css/admin.css') || !str_contains($html, 'Admin Layout Test')) {
    fwrite(STDERR, "Admin layout did not render expected HTML.\n");
    exit(1);
}

echo "Admin layout rendered.\n";

// End of file.
