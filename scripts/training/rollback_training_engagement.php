<?php

/**
 * Remove only the deterministic engagement fixtures created for the tenant
 * whose slug is exactly "training".
 */

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

try {
    $pdo = Database::connect($root);
    $statement = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug ORDER BY id ASC');
    $statement->execute(['slug' => 'training']);
    $ids = $statement->fetchAll(\PDO::FETCH_COLUMN);

    if (count($ids) !== 1) {
        throw new \RuntimeException(sprintf('Expected exactly one training tenant; found %d.', count($ids)));
    }

    $tenantId = (int) $ids[0];
    $pdo->beginTransaction();

    $deleteEvents = $pdo->prepare(
        "DELETE FROM exhibitions
         WHERE tenant_id = :tenant_id
           AND name IN (
             'Northstar: Recent Sculpture','Summer Group Exhibition','Open Studio Weekend',
             'Artist Talk: Structure and Balance','Winter Salon','Vermont Sculpture Walk',
             'Museum Collection Acquisition','Proposed Residency'
           )"
    );
    $deleteEvents->execute(['tenant_id' => $tenantId]);

    $deleteMessages = $pdo->prepare(
        "DELETE FROM contact_messages
         WHERE tenant_id = :tenant_id
           AND sender_email IN (
             'training-buyer+taylor@example.com',
             'training-curator+jordan@example.com',
             'training-visitor+sam@example.com'
           )"
    );
    $deleteMessages->execute(['tenant_id' => $tenantId]);

    $deleteSignups = $pdo->prepare(
        "DELETE FROM email_signups
         WHERE tenant_id = :tenant_id
           AND email IN (
             'training-list+one@example.com',
             'training-list+two@example.com',
             'training-list+pending@example.com',
             'training-list+duplicate@example.com'
           )"
    );
    $deleteSignups->execute(['tenant_id' => $tenantId]);

    $pdo->commit();

    echo sprintf("[PASS] Removed %d events, %d messages, and %d signups from tenant training.\n", $deleteEvents->rowCount(), $deleteMessages->rowCount(), $deleteSignups->rowCount());
} catch (\Throwable $exception) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

// End of file.
