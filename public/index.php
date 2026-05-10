<?php
/**
 * Public entry point for the Bxiie Artist CMS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;

$router = new Router($container);
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

// End of file.
