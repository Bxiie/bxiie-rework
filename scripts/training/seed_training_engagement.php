<?php

/**
 * Seed deterministic events, contact messages, and mailing-list records for the
 * ArtsFolio tenant whose slug is exactly "training".
 */

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

const TRAINING_SLUG = 'training';

/**
 * Return a single tenant ID for the immutable training slug.
 */
function resolveTrainingTenantId(\PDO $pdo): int
{
    $statement = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug ORDER BY id ASC');
    $statement->execute(['slug' => TRAINING_SLUG]);
    $ids = $statement->fetchAll(\PDO::FETCH_COLUMN);

    if (count($ids) !== 1) {
        throw new \RuntimeException(
            sprintf(
                'Expected exactly one tenant with slug %s; found %d. No changes were made.',
                TRAINING_SLUG,
                count($ids)
            )
        );
    }

    return (int) $ids[0];
}

/**
 * Assert that the expected table columns exist before any data is changed.
 * This converts schema drift into an early, readable failure.
 *
 * @param array<string, list<string>> $requirements
 */
function assertSchema(\PDO $pdo, array $requirements): void
{
    $statement = $pdo->prepare(
        'SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );

    foreach ($requirements as $table => $columns) {
        $statement->execute(['table_name' => $table]);
        $available = array_fill_keys($statement->fetchAll(\PDO::FETCH_COLUMN), true);

        foreach ($columns as $column) {
            if (!isset($available[$column])) {
                throw new \RuntimeException("Required column {$table}.{$column} is missing. No changes were made.");
            }
        }
    }
}

/**
 * Save the tenant's current engagement rows before replacing fixtures.
 */
function backupTrainingRows(\PDO $pdo, int $tenantId, string $root): string
{
    $configuredRoot = trim((string) getenv('ARTSFOLIO_TRAINING_BACKUP_ROOT'));
    $candidates = $configuredRoot !== ''
        ? [$configuredRoot]
        : [
            $root . '/storage/training-backups',
            rtrim(sys_get_temp_dir(), '/') . '/artsfolio-training-backups',
        ];

    $backupRoot = null;
    $failures = [];

    foreach ($candidates as $candidate) {
        if (!is_dir($candidate) && !mkdir($candidate, 0770, true) && !is_dir($candidate)) {
            $failures[] = "could not create {$candidate}";
            continue;
        }

        if (!is_writable($candidate)) {
            $failures[] = "not writable: {$candidate}";
            continue;
        }

        $backupRoot = $candidate;
        break;
    }

    if ($backupRoot === null) {
        throw new \RuntimeException(
            'Unable to select a writable training backup root: ' . implode('; ', $failures)
        );
    }

    $backupDir = sprintf(
        '%s/training-engagement-git-%s',
        rtrim($backupRoot, '/'),
        gmdate('YmdHis')
    );

    if (!mkdir($backupDir, 0770, true) && !is_dir($backupDir)) {
        throw new \RuntimeException("Unable to create backup directory: {$backupDir}");
    }

    foreach (['exhibitions', 'contact_messages', 'email_signups'] as $table) {
        $statement = $pdo->prepare("SELECT * FROM {$table} WHERE tenant_id = :tenant_id ORDER BY id ASC");
        $statement->execute(['tenant_id' => $tenantId]);
        $json = json_encode($statement->fetchAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents("{$backupDir}/{$table}.json", $json . PHP_EOL) === false) {
            throw new \RuntimeException("Unable to write backup for table {$table}.");
        }
    }

    $manifest = [
        'created_at_utc' => gmdate(DATE_ATOM),
        'tenant_slug' => TRAINING_SLUG,
        'tenant_id' => $tenantId,
        'tables' => ['exhibitions', 'contact_messages', 'email_signups'],
    ];
    $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($manifestJson === false || file_put_contents("{$backupDir}/manifest.json", $manifestJson . PHP_EOL) === false) {
        throw new \RuntimeException('Unable to write the training backup manifest.');
    }

    return $backupDir;
}

/**
 * Insert the deterministic event fixtures.
 */
function seedEvents(\PDO $pdo, int $tenantId): void
{
    $names = [
        'Northstar: Recent Sculpture',
        'Summer Group Exhibition',
        'Open Studio Weekend',
        'Artist Talk: Structure and Balance',
        'Winter Salon',
        'Vermont Sculpture Walk',
        'Museum Collection Acquisition',
        'Proposed Residency',
    ];

    $placeholders = implode(', ', array_fill(0, count($names), '?'));
    $delete = $pdo->prepare("DELETE FROM exhibitions WHERE tenant_id = ? AND name IN ({$placeholders})");
    $delete->execute(array_merge([$tenantId], $names));

    $insert = $pdo->prepare(
        'INSERT INTO exhibitions (
            uuid, tenant_id, exhibition_date, name, exhibition_type, location,
            city, state_region, work_name, notes, sort_order, status, created_at, updated_at
         ) VALUES (
            UUID(), :tenant_id, :exhibition_date, :name, :exhibition_type, :location,
            :city, :state_region, :work_name, :notes, :sort_order, :status,
            :created_at, :updated_at
         )'
    );

    $rows = [
        ['June 20 - September 7, 2026', 'Northstar: Recent Sculpture', 'Solo exhibition', 'Cedar Line Gallery', 'Woodstock', 'Vermont', 'Meridian No. 3; Counterweight; Folded Horizon', 'A focused presentation of recent geometric sculpture. Public URL: https://training.artsfol.io/events/northstar-recent-sculpture', 10, 'active', -45, 0],
        ['August 14 - October 4, 2026', 'Summer Group Exhibition', 'Group exhibition', 'Granite House Arts', 'Manchester', 'Vermont', 'Quiet Vector; Field Notes I', 'A regional exhibition examining pattern, structure, and repetition. Public URL: https://training.artsfol.io/events/summer-group-exhibition', 30, 'active', -35, 0],
        ['September 19-20, 2026', 'Open Studio Weekend', 'Open studio', 'Northstar Studio', 'Perkinsville', 'Vermont', null, 'Studio demonstrations, recent work, and informal conversations with the artist. Public URL: https://training.artsfol.io/events/open-studio-weekend', 20, 'active', -28, 0],
        ['October 8, 2026', 'Artist Talk: Structure and Balance', 'Artist talk', 'Cedar Line Gallery', 'Woodstock', 'Vermont', 'Meridian No. 3', 'An evening conversation about geometric systems, fabrication, and material balance. Public URL: https://training.artsfol.io/events/artist-talk-structure-balance', 40, 'active', -20, 0],
        ['December 6, 2025 - January 18, 2026', 'Winter Salon', 'Group exhibition', 'Juniper Room', 'Brattleboro', 'Vermont', 'Blue Interval', 'Annual winter exhibition of small works. Public URL: https://training.artsfol.io/events/winter-salon', 50, 'active', -210, -175],
        ['May 3 - November 2, 2025', 'Vermont Sculpture Walk', 'Outdoor exhibition', 'Riverbend Art Grounds', 'Windsor', 'Vermont', 'River Geometry', 'Seasonal outdoor sculpture installation along the river path. Public URL: https://training.artsfol.io/events/vermont-sculpture-walk', 60, 'active', -430, -250],
        ['March 2024', 'Museum Collection Acquisition', 'Collection milestone', 'North Valley Museum of Art', 'Montpelier', 'Vermont', 'Folded Horizon', 'Folded Horizon entered the museum permanent collection. Public URL: https://training.artsfol.io/events/museum-collection-acquisition', 70, 'active', -850, -850],
        ['January - March 2027', 'Proposed Residency', 'Residency', 'Stonebridge Arts Center', 'North Adams', 'Massachusetts', null, 'Draft training record for demonstrating editing, publication status, and ordering. Public URL: https://training.artsfol.io/events/proposed-residency', 5, 'hidden', -5, 0],
    ];

    foreach ($rows as $row) {
        $insert->execute([
            'tenant_id' => $tenantId,
            'exhibition_date' => $row[0],
            'name' => $row[1],
            'exhibition_type' => $row[2],
            'location' => $row[3],
            'city' => $row[4],
            'state_region' => $row[5],
            'work_name' => $row[6],
            'notes' => $row[7],
            'sort_order' => $row[8],
            'status' => $row[9],
            'created_at' => gmdate('Y-m-d H:i:s', strtotime($row[10] . ' days')),
            'updated_at' => gmdate('Y-m-d H:i:s', strtotime($row[11] . ' days')),
        ]);
    }
}

/**
 * Insert the deterministic contact-message fixtures.
 */
function seedMessages(\PDO $pdo, int $tenantId): void
{
    $emails = [
        'training-buyer+taylor@example.com',
        'training-curator+jordan@example.com',
        'training-visitor+sam@example.com',
    ];
    $placeholders = implode(', ', array_fill(0, count($emails), '?'));
    $delete = $pdo->prepare("DELETE FROM contact_messages WHERE tenant_id = ? AND sender_email IN ({$placeholders})");
    $delete->execute(array_merge([$tenantId], $emails));

    $insert = $pdo->prepare(
        'INSERT INTO contact_messages (
            tenant_id, sender_name, sender_email, name, email, subject, message,
            ip_address, user_agent, country, region, city, status, created_at, updated_at
         ) VALUES (
            :tenant_id, :sender_name, :sender_email, :name, :email, :subject, :message,
            :ip_address, :user_agent, :country, :region, :city, :status,
            :created_at, :updated_at
         )'
    );

    $rows = [
        ['Taylor Reed', 'training-buyer+taylor@example.com', 'Question about Meridian No. 3', 'Is Meridian No. 3 available for viewing before purchase? I am also interested in the packed dimensions and estimated delivery time to Boston.', '192.0.2.41', 'Massachusetts', 'Boston', 'new', -2, -2],
        ['Jordan Lee', 'training-curator+jordan@example.com', 'Group exhibition inquiry', 'We are planning a group exhibition on constructed form and would like to discuss including two works from Northstar Studio.', '198.51.100.27', 'New York', 'Albany', 'read', -72, -48],
        ['Sam Rivera', 'training-visitor+sam@example.com', 'Open studio hours', 'Are appointments available on weekday afternoons?', '203.0.113.19', 'Vermont', 'Rutland', 'archived', -288, -192],
    ];

    foreach ($rows as $row) {
        $insert->execute([
            'tenant_id' => $tenantId,
            'sender_name' => $row[0],
            'sender_email' => $row[1],
            'name' => $row[0],
            'email' => $row[1],
            'subject' => $row[2],
            'message' => $row[3],
            'ip_address' => $row[4],
            'user_agent' => 'ArtsFolio Training Fixture/1.0',
            'country' => 'United States',
            'region' => $row[5],
            'city' => $row[6],
            'status' => $row[7],
            'created_at' => gmdate('Y-m-d H:i:s', strtotime($row[8] . ' hours')),
            'updated_at' => gmdate('Y-m-d H:i:s', strtotime($row[9] . ' hours')),
        ]);
    }
}

/**
 * Insert or refresh deterministic mailing-list fixtures.
 */
function seedSignups(\PDO $pdo, int $tenantId): void
{
    $insert = $pdo->prepare(
        'INSERT INTO email_signups (
            tenant_id, email, name, source, notes, ip_address, user_agent,
            country, region, city, consent_status, confirmed_at, unsubscribed_at,
            created_at, updated_at
         ) VALUES (
            :tenant_id, :email, :name, :source, :notes, :ip_address, :user_agent,
            :country, :region, :city, :consent_status,
            CASE WHEN :confirmed_days IS NULL THEN NULL ELSE UTC_TIMESTAMP() + INTERVAL :confirmed_days_again DAY END,
            NULL,
            :created_at, :updated_at
         )
         ON DUPLICATE KEY UPDATE
            name = VALUES(name), source = VALUES(source), notes = VALUES(notes),
            ip_address = VALUES(ip_address), user_agent = VALUES(user_agent),
            country = VALUES(country), region = VALUES(region), city = VALUES(city),
            consent_status = VALUES(consent_status), confirmed_at = VALUES(confirmed_at),
            unsubscribed_at = VALUES(unsubscribed_at), updated_at = VALUES(updated_at)'
    );

    $rows = [
        ['training-list+one@example.com', 'Alex Morgan', 'footer_signup', 'Confirmed training subscriber created by seed_training_engagement.php.', '192.0.2.51', 'Vermont', 'Burlington', 'confirmed', -20, -20, -20],
        ['training-list+two@example.com', 'Jamie Chen', 'contact_page', 'Confirmed training subscriber created by seed_training_engagement.php.', '198.51.100.52', 'Massachusetts', 'Northampton', 'confirmed', -9, -9, -9],
        ['training-list+pending@example.com', 'Riley Brooks', 'mailing_list_dialog', 'Pending confirmation fixture for the training video.', '203.0.113.53', 'New Hampshire', 'Lebanon', 'pending', null, -1, -1],
        ['training-list+duplicate@example.com', 'Cameron Wells', 'footer_signup', 'Represents an address submitted more than once; the tenant/email key retains one row.', '192.0.2.54', 'Vermont', 'Springfield', 'confirmed', -5, -6, -5],
    ];

    foreach ($rows as $row) {
        $insert->execute([
            'tenant_id' => $tenantId,
            'email' => $row[0],
            'name' => $row[1],
            'source' => $row[2],
            'notes' => $row[3],
            'ip_address' => $row[4],
            'user_agent' => 'ArtsFolio Training Fixture/1.0',
            'country' => 'United States',
            'region' => $row[5],
            'city' => $row[6],
            'consent_status' => $row[7],
            'confirmed_at' => $row[8] === null ? null : gmdate('Y-m-d H:i:s', strtotime($row[8] . ' days')),
            'created_at' => gmdate('Y-m-d H:i:s', strtotime($row[9] . ' days')),
            'updated_at' => gmdate('Y-m-d H:i:s', strtotime($row[10] . ' days')),
        ]);
    }
}

/**
 * Verify the exact deterministic fixture counts.
 */
function verifyCounts(\PDO $pdo, int $tenantId): void
{
    $checks = [
        'events' => [
            'sql' => "SELECT COUNT(*) FROM exhibitions WHERE tenant_id = :tenant_id AND name IN ('Northstar: Recent Sculpture','Summer Group Exhibition','Open Studio Weekend','Artist Talk: Structure and Balance','Winter Salon','Vermont Sculpture Walk','Museum Collection Acquisition','Proposed Residency')",
            'expected' => 8,
        ],
        'messages' => [
            'sql' => "SELECT COUNT(*) FROM contact_messages WHERE tenant_id = :tenant_id AND sender_email IN ('training-buyer+taylor@example.com','training-curator+jordan@example.com','training-visitor+sam@example.com')",
            'expected' => 3,
        ],
        'signups' => [
            'sql' => "SELECT COUNT(*) FROM email_signups WHERE tenant_id = :tenant_id AND email IN ('training-list+one@example.com','training-list+two@example.com','training-list+pending@example.com','training-list+duplicate@example.com')",
            'expected' => 4,
        ],
    ];

    foreach ($checks as $label => $check) {
        $statement = $pdo->prepare($check['sql']);
        $statement->execute(['tenant_id' => $tenantId]);
        $actual = (int) $statement->fetchColumn();

        if ($actual !== $check['expected']) {
            throw new \RuntimeException("Verification failed for {$label}: expected {$check['expected']}, found {$actual}.");
        }

        echo sprintf("[PASS] Verified %d %s fixtures.\n", $actual, $label);
    }
}

try {
    $pdo = Database::connect($root);
    $tenantId = resolveTrainingTenantId($pdo);

    assertSchema($pdo, [
        'exhibitions' => ['id', 'uuid', 'tenant_id', 'exhibition_date', 'name', 'exhibition_type', 'location', 'city', 'state_region', 'work_name', 'notes', 'sort_order', 'status', 'created_at', 'updated_at'],
        'contact_messages' => ['id', 'tenant_id', 'sender_name', 'sender_email', 'name', 'email', 'subject', 'message', 'ip_address', 'user_agent', 'country', 'region', 'city', 'status', 'created_at', 'updated_at'],
        'email_signups' => ['id', 'tenant_id', 'email', 'name', 'source', 'notes', 'ip_address', 'user_agent', 'country', 'region', 'city', 'consent_status', 'confirmed_at', 'unsubscribed_at', 'created_at', 'updated_at'],
    ]);

    $backupDir = backupTrainingRows($pdo, $tenantId, $root);
    echo "[PASS] Backed up current training records to {$backupDir}.\n";

    $pdo->beginTransaction();
    seedEvents($pdo, $tenantId);
    seedMessages($pdo, $tenantId);
    seedSignups($pdo, $tenantId);
    verifyCounts($pdo, $tenantId);
    $pdo->commit();

    echo "[PASS] Training engagement fixtures deployed for tenant training (ID {$tenantId}).\n";
} catch (\Throwable $exception) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

// End of file.
