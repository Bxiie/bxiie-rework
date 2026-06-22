<?php

declare(strict_types=1);

use App\Platform\Analytics\AnalyticsRollupService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$options = getopt('', ['days::', 'from::', 'to::', 'help']);
if (isset($options['help'])) {
    echo <<<'HELP'
Usage:
  php scripts/maintenance/rebuild_analytics_rollups.php [days]
  php scripts/maintenance/rebuild_analytics_rollups.php --days=30
  php scripts/maintenance/rebuild_analytics_rollups.php --from="2026-06-01 00:00:00" --to="2026-06-02 00:00:00"

The rebuild processes one UTC hour and one UTC day per transaction. The range
end is exclusive for event selection. Re-running the same range is safe.
HELP;
    echo PHP_EOL;
    exit(0);
}

$service = new AnalyticsRollupService(Database::connect($root));
$utc = new \DateTimeZone('UTC');

if (isset($options['from']) || isset($options['to'])) {
    if (!isset($options['from'], $options['to'])) {
        fwrite(STDERR, "Both --from and --to are required for a range rebuild.\n");
        exit(2);
    }

    try {
        $from = new \DateTimeImmutable((string) $options['from'], $utc);
        $to = new \DateTimeImmutable((string) $options['to'], $utc);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Invalid --from or --to value: ' . $exception->getMessage() . PHP_EOL);
        exit(2);
    }

    $result = $service->rebuildRange($from, $to);
} else {
    $positionalDays = $argv[1] ?? null;
    $days = isset($options['days'])
        ? (int) $options['days']
        : (is_string($positionalDays) && ctype_digit($positionalDays) ? (int) $positionalDays : 30);
    $result = $service->rebuildRecent(max(1, $days));
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

// End of file.
