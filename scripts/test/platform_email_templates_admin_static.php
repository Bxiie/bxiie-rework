#!/usr/bin/php
<?php

/**
 * Static regression checks for the platform email-template editor.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/Admin/EmailTemplatesController.php';
$routesPath = $root . '/app/Http/Routes/platform.php';
$layoutPath = $root . '/app/Http/View/AdminLayout.php';

foreach ([$controllerPath, $routesPath, $layoutPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing required file: {$path}\n");
        exit(1);
    }
}

$controller = (string) file_get_contents($controllerPath);
$routes = (string) file_get_contents($routesPath);
$layout = (string) file_get_contents($layoutPath);

$checks = [
    'GET route exists' => str_contains($routes, "\$router->get('/platform/admin/email-templates'"),
    'POST route exists' => str_contains($routes, "\$router->post('/platform/admin/email-templates'"),
    'navigation item exists' => str_contains($layout, "'email_templates' => ['/platform/admin/email-templates', 'Email Templates']"),
    'owner/admin authorization is enforced' => str_contains($controller, 'Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN'),
    'CSRF validation is enforced' => str_contains($controller, '$this->csrf->validate'),
    'save target comes from inventory' => str_contains($controller, '!isset($templates[$relativePath])'),
    'symlinks are excluded' => str_contains($controller, '$file->isLink()'),
    'canonical root containment is checked' => str_contains($controller, 'str_starts_with($absolutePath, $root . DIRECTORY_SEPARATOR)'),
    'writes are atomic' => str_contains($controller, 'tempnam(') && str_contains($controller, 'rename($temporary, $target)'),
    'updates are audited' => str_contains($controller, 'platform.email_template.updated'),
    'template size is bounded' => str_contains($controller, 'MAX_TEMPLATE_BYTES'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

echo "[PASS] Platform email-template editor static check passed.\n";

// End of file.
