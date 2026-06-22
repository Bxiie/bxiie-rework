<?php

declare(strict_types=1);

namespace App\Platform\Monitoring;

/**
 * Aggregates health metrics and renders console/email summaries.
 */
final class HealthReport
{
    /** @param list<HealthMetric> $metrics */
    public function __construct(
        public readonly array $metrics,
        public readonly string $hostName,
        public readonly string $startedAt,
        public readonly float $durationSeconds,
    ) {
    }

    public function overallStatus(): string
    {
        foreach ($this->metrics as $metric) {
            if ($metric->status === HealthMetric::CRIT) {
                return HealthMetric::CRIT;
            }
        }
        foreach ($this->metrics as $metric) {
            if ($metric->status === HealthMetric::WARN) {
                return HealthMetric::WARN;
            }
        }

        return HealthMetric::OK;
    }

    /** @return list<HealthMetric> */
    public function troubleMetrics(): array
    {
        return array_values(array_filter(
            $this->metrics,
            static fn (HealthMetric $metric): bool => $metric->isTrouble(),
        ));
    }

    public function fingerprint(): string
    {
        $trouble = array_map(
            static fn (HealthMetric $metric): string => $metric->status . ':' . $metric->name,
            $this->troubleMetrics(),
        );
        sort($trouble);

        return hash('sha256', implode('|', $trouble));
    }

    public function counts(): array
    {
        $counts = [HealthMetric::OK => 0, HealthMetric::WARN => 0, HealthMetric::CRIT => 0, HealthMetric::INFO => 0];
        foreach ($this->metrics as $metric) {
            $counts[$metric->status] = ($counts[$metric->status] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return list<HealthMetric> */
    public function metricsBySeverity(bool $troubleOnly = false): array
    {
        $metrics = $troubleOnly ? $this->troubleMetrics() : $this->metrics;
        $priority = [
            HealthMetric::CRIT => 0,
            HealthMetric::WARN => 1,
            HealthMetric::OK => 2,
            HealthMetric::INFO => 3,
        ];

        usort($metrics, static function (HealthMetric $left, HealthMetric $right) use ($priority): int {
            $statusOrder = ($priority[$left->status] ?? 99) <=> ($priority[$right->status] ?? 99);
            return $statusOrder !== 0 ? $statusOrder : strcmp($left->name, $right->name);
        });

        return $metrics;
    }

    public function toText(bool $troubleOnly = false): string
    {
        $counts = $this->counts();
        $lines = [
            sprintf(
                'ArtsFolio operations health: %s on %s at %s (%.2fs)',
                $this->overallStatus(),
                $this->hostName,
                $this->startedAt,
                $this->durationSeconds,
            ),
            sprintf(
                'Summary: %d OK, %d WARN, %d CRIT, %d INFO',
                $counts[HealthMetric::OK],
                $counts[HealthMetric::WARN],
                $counts[HealthMetric::CRIT],
                $counts[HealthMetric::INFO],
            ),
            '',
        ];

        $metrics = $this->metricsBySeverity($troubleOnly);
        foreach ($metrics as $metric) {
            $lines[] = $metric->toText();
        }

        return implode("\n", $lines) . "\n";
    }

    public function toArray(): array
    {
        return [
            'overall_status' => $this->overallStatus(),
            'host_name' => $this->hostName,
            'started_at' => $this->startedAt,
            'duration_seconds' => round($this->durationSeconds, 3),
            'counts' => $this->counts(),
            'metrics' => array_map(static fn (HealthMetric $metric): array => $metric->toArray(), $this->metrics),
        ];
    }
}

// End of file.
