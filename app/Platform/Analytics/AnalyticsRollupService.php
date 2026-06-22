<?php

declare(strict_types=1);

namespace App\Platform\Analytics;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Rebuilds analytics rollups from immutable raw events in bounded buckets.
 *
 * Hourly and daily buckets are processed independently so MariaDB never needs
 * to materialize a multi-day aggregation in one temporary table. Each bucket
 * commits separately, making interrupted rebuilds safe to rerun.
 */
final class AnalyticsRollupService
{
    private const MAX_DAYS = 365;

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Rebuild the most recent number of calendar days through the current hour.
     *
     * @return array{hourly_rows:int,daily_rows:int,hourly_buckets:int,daily_buckets:int,days:int,from:string,to:string}
     */
    public function rebuildRecent(int $days = 3): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $timezone = new DateTimeZone('UTC');
        $to = new DateTimeImmutable('now', $timezone);
        $from = $to->setTime(0, 0)->sub(new DateInterval('P' . ($days - 1) . 'D'));

        $result = $this->rebuildRange($from, $to);
        $result['days'] = $days;

        return $result;
    }

    /**
     * Rebuild rollups for a bounded UTC range.
     *
     * The end instant is exclusive for raw-event selection. The current partial
     * hour and current partial day are included and can be safely rebuilt later.
     *
     * @return array{hourly_rows:int,daily_rows:int,hourly_buckets:int,daily_buckets:int,from:string,to:string}
     */
    public function rebuildRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $utc = new DateTimeZone('UTC');
        $from = $from->setTimezone($utc);
        $to = $to->setTimezone($utc);

        if ($to <= $from) {
            throw new \InvalidArgumentException('Analytics rollup end time must be after the start time.');
        }

        $hourlyRows = 0;
        $dailyRows = 0;
        $hourlyBuckets = 0;
        $dailyBuckets = 0;

        $hourCursor = $from->setTime((int) $from->format('H'), 0, 0);
        $hourLimit = $to->setTime((int) $to->format('H'), 0, 0);
        if ($to > $hourLimit) {
            $hourLimit = $hourLimit->add(new DateInterval('PT1H'));
        }
        while ($hourCursor < $hourLimit) {
            $bucketEnd = $hourCursor->add(new DateInterval('PT1H'));
            $hourlyRows += $this->rebuildHourlyBucket($hourCursor, $bucketEnd);
            ++$hourlyBuckets;
            $hourCursor = $bucketEnd;
        }

        $dayCursor = $from->setTime(0, 0, 0);
        $dayLimit = $to->setTime(0, 0, 0);
        if ($to > $dayLimit) {
            $dayLimit = $dayLimit->add(new DateInterval('P1D'));
        }
        while ($dayCursor < $dayLimit) {
            $bucketEnd = $dayCursor->add(new DateInterval('P1D'));
            $dailyRows += $this->rebuildDailyBucket($dayCursor, $bucketEnd);
            ++$dailyBuckets;
            $dayCursor = $bucketEnd;
        }

        return [
            'hourly_rows' => $hourlyRows,
            'daily_rows' => $dailyRows,
            'hourly_buckets' => $hourlyBuckets,
            'daily_buckets' => $dailyBuckets,
            'from' => $from->format(DATE_ATOM),
            'to' => $to->format(DATE_ATOM),
        ];
    }

    /** Rebuild one UTC hour and return the inserted row count. */
    private function rebuildHourlyBucket(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return $this->rebuildBucket(
            'analytics_rollups_hourly',
            'bucket_start',
            $start->format('Y-m-d H:00:00'),
            $start,
            $end
        );
    }

    /** Rebuild one UTC day and return the inserted row count. */
    private function rebuildDailyBucket(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return $this->rebuildBucket(
            'analytics_rollups_daily',
            'bucket_date',
            $start->format('Y-m-d'),
            $start,
            $end
        );
    }

    /**
     * Delete and rebuild one exact bucket in its own transaction.
     *
     * Table and column names are selected only from private constant call sites;
     * event times and bucket values remain parameterized.
     */
    private function rebuildBucket(
        string $table,
        string $bucketColumn,
        string $bucketValue,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): int {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare(
                "DELETE FROM {$table} WHERE {$bucketColumn} = :bucket_value"
            );
            $delete->execute(['bucket_value' => $bucketValue]);

            $insert = $this->pdo->prepare(
                "INSERT INTO {$table} (
                    {$bucketColumn}, tenant_key, event_type, path, entity_type,
                    entity_id, country, region, city, dimension_hash,
                    event_count, unique_visitor_count, first_event_at, last_event_at
                )
                SELECT
                    :bucket_value,
                    COALESCE(tenant_id, 0),
                    event_type,
                    COALESCE(path, ''),
                    COALESCE(entity_type, ''),
                    COALESCE(entity_id, 0),
                    COALESCE(country, ''),
                    COALESCE(region, ''),
                    COALESCE(city, ''),
                    SHA2(CONCAT_WS(
                        CHAR(31),
                        COALESCE(path, ''),
                        COALESCE(entity_type, ''),
                        COALESCE(entity_id, 0),
                        COALESCE(country, ''),
                        COALESCE(region, ''),
                        COALESCE(city, '')
                    ), 256),
                    COUNT(*),
                    COUNT(DISTINCT ip_hash),
                    MIN(created_at),
                    MAX(created_at)
                FROM analytics_events
                WHERE created_at >= :event_start
                  AND created_at < :event_end
                GROUP BY
                    COALESCE(tenant_id, 0),
                    event_type,
                    COALESCE(path, ''),
                    COALESCE(entity_type, ''),
                    COALESCE(entity_id, 0),
                    COALESCE(country, ''),
                    COALESCE(region, ''),
                    COALESCE(city, '')"
            );
            $insert->execute([
                'bucket_value' => $bucketValue,
                'event_start' => $start->format('Y-m-d H:i:s'),
                'event_end' => $end->format('Y-m-d H:i:s'),
            ]);

            $rows = $insert->rowCount();
            $this->pdo->commit();

            return $rows;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}

// End of file.
