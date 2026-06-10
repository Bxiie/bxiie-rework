<?php
/**
 * Application bootstrap and tiny dependency container.
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

$config = require __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name']);
    session_start();
}

$container = [
    'config' => $config,
    'db' => App\Core\Database::connect($config['database_path']),
];

// End of file.
