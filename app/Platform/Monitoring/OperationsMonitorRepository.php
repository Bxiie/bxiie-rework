<?php

declare(strict_types=1);

namespace App\Platform\Monitoring;

use PDO;

/**
 * Persists monitor runs and notification suppression state.
 */
final class OperationsMonitorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordRun(HealthReport $report): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO operations_monitor_runs (
                host_name, overall_status, metric_count, warning_count, critical_count, duration_ms, report_json, created_at
             ) VALUES (
                :host_name, :overall_status, :metric_count, :warning_count, :critical_count, :duration_ms, :report_json, UTC_TIMESTAMP()
             )"
        );
        $counts = $report->counts();
        $stmt->execute([
            'host_name' => $report->hostName,
            'overall_status' => $report->overallStatus(),
            'metric_count' => count($report->metrics),
            'warning_count' => (int) ($counts[HealthMetric::WARN] ?? 0),
            'critical_count' => (int) ($counts[HealthMetric::CRIT] ?? 0),
            'duration_ms' => (int) round($report->durationSeconds * 1000),
            'report_json' => json_encode($report->toArray(), JSON_THROW_ON_ERROR),
        ]);

        $runId = (int) $this->pdo->lastInsertId();
        if ($runId <= 0) {
            $runId = (int) $this->pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
        }
        if ($runId <= 0) {
            throw new \RuntimeException('Unable to determine operations monitor run ID after insert.');
        }

        $metricStmt = $this->pdo->prepare(
            "INSERT INTO operations_monitor_metrics (
                run_id, metric_name, metric_status, expected_value, actual_value, actual_numeric, detail_text, created_at
             ) VALUES (
                :run_id, :metric_name, :metric_status, :expected_value, :actual_value, :actual_numeric, :detail_text, UTC_TIMESTAMP()
             )"
        );
        foreach ($report->metrics as $metric) {
            $metricStmt->execute([
                'run_id' => $runId,
                'metric_name' => $metric->name,
                'metric_status' => $metric->status,
                'expected_value' => $metric->expected,
                'actual_value' => $metric->actual,
                'actual_numeric' => $this->numericValue($metric->actual),
                'detail_text' => $metric->detail !== '' ? $metric->detail : null,
            ]);
        }

        $this->pdo->exec("DELETE FROM operations_monitor_runs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)");

        return $runId;
    }

    public function state(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM operations_monitor_state WHERE id = 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return $row ?: [
            'id' => 1,
            'last_status' => 'UNKNOWN',
            'last_fingerprint' => '',
            'last_alert_at' => null,
            'last_morning_report_date' => null,
            'last_evening_report_date' => null,
            'last_boot_id' => null,
            'last_component_states_json' => null,
        ];
    }

    public function updateState(
        HealthReport $report,
        ?string $alertAt,
        ?string $morningDate,
        ?string $eveningDate,
        ?string $bootId,
        ?string $componentStatesJson,
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO operations_monitor_state (
                id,
                last_status,
                last_fingerprint,
                last_alert_at,
                last_morning_report_date,
                last_evening_report_date,
                last_boot_id,
                last_component_states_json,
                updated_at
             ) VALUES (
                1,
                :last_status,
                :last_fingerprint,
                :last_alert_at,
                :last_morning_report_date,
                :last_evening_report_date,
                :last_boot_id,
                :last_component_states_json,
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                last_status = VALUES(last_status),
                last_fingerprint = VALUES(last_fingerprint),
                last_alert_at = VALUES(last_alert_at),
                last_morning_report_date = VALUES(last_morning_report_date),
                last_evening_report_date = VALUES(last_evening_report_date),
                last_boot_id = VALUES(last_boot_id),
                last_component_states_json = VALUES(last_component_states_json),
                updated_at = UTC_TIMESTAMP()"
        );
        $stmt->execute([
            'last_status' => $report->overallStatus(),
            'last_fingerprint' => $report->fingerprint(),
            'last_alert_at' => $alertAt,
            'last_morning_report_date' => $morningDate,
            'last_evening_report_date' => $eveningDate,
            'last_boot_id' => $bootId,
            'last_component_states_json' => $componentStatesJson,
        ]);
    }

    public function latestRuns(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->query(
            "SELECT id, host_name, overall_status, metric_count, warning_count, critical_count, duration_ms, created_at
             FROM operations_monitor_runs ORDER BY id DESC LIMIT {$limit}"
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function run(int $runId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM operations_monitor_runs WHERE id = :id');
        $stmt->execute(['id' => $runId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            return null;
        }
        $metrics = $this->pdo->prepare(
            "SELECT metric_name, metric_status, expected_value, actual_value, actual_numeric, detail_text, created_at
             FROM operations_monitor_metrics WHERE run_id = :run_id
             ORDER BY FIELD(metric_status, 'CRIT', 'WARN', 'OK', 'INFO'), metric_name"
        );
        $metrics->execute(['run_id' => $runId]);
        $run['metrics'] = $metrics->fetchAll(PDO::FETCH_ASSOC);
        return $run;
    }

    public function latestMetricRows(): array
    {
        $stmt = $this->pdo->query(
            "SELECT m.metric_name, m.metric_status, m.expected_value, m.actual_value, m.actual_numeric, m.detail_text, m.created_at, m.run_id
             FROM operations_monitor_metrics m
             JOIN (SELECT metric_name, MAX(id) AS max_id FROM operations_monitor_metrics GROUP BY metric_name) latest
               ON latest.max_id = m.id
             ORDER BY FIELD(m.metric_status, 'CRIT','WARN','OK','INFO'), m.metric_name"
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function metricHistory(string $metricName, int $days = 7, int $limit = 500): array
    {
        $days = max(1, min(90, $days));
        $limit = max(2, min(2000, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT run_id, metric_status, actual_value, actual_numeric, expected_value, detail_text, created_at
             FROM operations_monitor_metrics
             WHERE metric_name = :metric_name
               AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$days} DAY)
             ORDER BY created_at ASC
             LIMIT {$limit}"
        );
        $stmt->execute(['metric_name' => $metricName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function numericValue(string $actual): ?string
    {
        if (preg_match('/-?[0-9]+(?:\.[0-9]+)?/', str_replace(',', '', $actual), $match) !== 1) {
            return null;
        }
        return $match[0];
    }

    public function platformAdminRecipients(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT LOWER(TRIM(u.email)) AS email,
                    NULLIF(TRIM(u.display_name), '') AS display_name
             FROM users u
             JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id IS NULL
             JOIN roles r ON r.id = ra.role_id
             WHERE r.scope = 'platform'
               AND r.slug IN ('owner', 'admin', 'platform_admin')
               AND COALESCE(u.status, 'active') = 'active'
               AND u.email IS NOT NULL
               AND TRIM(u.email) <> ''
             UNION
             SELECT DISTINCT LOWER(TRIM(u.email)) AS email,
                    NULLIF(TRIM(u.display_name), '') AS display_name
             FROM users u
             JOIN platform_roles pr ON pr.user_id = u.id
             WHERE pr.role IN ('owner', 'admin')
               AND COALESCE(u.status, 'active') = 'active'
               AND u.email IS NOT NULL
               AND TRIM(u.email) <> ''
             ORDER BY email"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}

// End of file.
