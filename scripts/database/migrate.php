<?php

declare(strict_types=1);

/**
 * Runs unapplied SQL migrations against the configured MariaDB database.
 * MariaDB DDL performs implicit commits, so migrations are not wrapped in a transaction.
 */

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

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

$checksumColumnExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'schema_migrations'
       AND column_name = 'checksum_sha256'"
)->fetchColumn() > 0;

$applied = [];
$selectColumns = $checksumColumnExists ? 'migration, checksum_sha256' : 'migration';
$stmt = $pdo->query('SELECT ' . $selectColumns . ' FROM schema_migrations');

foreach ($stmt->fetchAll() as $row) {
    $applied[(string) $row['migration']] = $checksumColumnExists
        ? (string) ($row['checksum_sha256'] ?? '')
        : '';
}

foreach ($files as $file) {
    $name = basename($file);

    if (array_key_exists($name, $applied)) {
        if ($checksumColumnExists) {
            $expectedChecksum = hash_file('sha256', $file);
            $recordedChecksum = (string) $applied[$name];
            if ($recordedChecksum !== '' && !hash_equals($expectedChecksum, $recordedChecksum)) {
                fwrite(STDERR, "Migration checksum mismatch: {$name}\n");
                exit(1);
            }
        }
        echo "Already applied: {$name}\n";
        continue;
    }

    echo "Applying: {$name}\n";

    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);

        $checksumColumnExists = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'schema_migrations'
               AND column_name = 'checksum_sha256'"
        )->fetchColumn() > 0;

        if ($checksumColumnExists) {
            $insert = $pdo->prepare('INSERT INTO schema_migrations (migration, checksum_sha256) VALUES (:migration, :checksum)');
            $insert->execute(['migration' => $name, 'checksum' => hash_file('sha256', $file)]);
        } else {
            $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
            $insert->execute(['migration' => $name]);
        }

        echo "Applied: {$name}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed migration {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

$checksumColumnExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'schema_migrations'
       AND column_name = 'checksum_sha256'"
)->fetchColumn() > 0;

if ($checksumColumnExists) {
    $updateChecksum = $pdo->prepare(
        "UPDATE schema_migrations SET checksum_sha256 = :checksum WHERE migration = :migration AND (checksum_sha256 IS NULL OR checksum_sha256 = '')"
    );
    foreach ($files as $file) {
        $updateChecksum->execute([
            'migration' => basename($file),
            'checksum' => hash_file('sha256', $file),
        ]);
    }
}

echo "Migrations complete.\n";

// End of file.
