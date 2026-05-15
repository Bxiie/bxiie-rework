<?php

declare(strict_types=1);

/**
 * Runs unapplied SQL migrations against the configured MariaDB database.
 */

$root = dirname(__DIR__, 2);
$configFile = $root . '/config/database.php';

if (!file_exists($configFile)) {
    fwrite(STDERR, "Missing config/database.php\n");
    exit(1);
}

$config = require $configFile;

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$migrationDir = $root . '/database/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

$applied = [];
$stmt = $pdo->query('SELECT migration FROM schema_migrations');
foreach ($stmt->fetchAll() as $row) {
    $applied[$row['migration']] = true;
}

foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo "Already applied: {$name}\n";
        continue;
    }

    echo "Applying: {$name}\n";

    $sql = file_get_contents($file);

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);

        $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $insert->execute(['migration' => $name]);

        $pdo->commit();
        echo "Applied: {$name}\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Failed migration {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Migrations complete.\n";

// End of file.
