<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Creates a PDO connection using config/database.php.
 */
final class Database
{
    public static function connect(string $root): PDO
    {
        $configFile = $root . '/config/database.php';

        if (!file_exists($configFile)) {
            throw new \RuntimeException('Missing config/database.php');
        }

        $config = require $configFile;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

// End of file.
