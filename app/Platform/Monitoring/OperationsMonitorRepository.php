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
                host_name,
                overall_status,
                metric_count,
                warning_count,
                critical_count,
                duration_ms,
                report_json,
                created_at
             ) VALUES (
                :host_name,
                :overall_status,
                :metric_count,
                :warning_count,
                :critical_count,
                :duration_ms,
                :report_json,
                UTC_TIMESTAMP()
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

        $this->pdo->exec("DELETE FROM operations_monitor_runs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)");

        $runId = (int) $this->pdo->lastInsertId();
        if ($runId <= 0) {
            $runId = (int) $this->pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
        }
        if ($runId <= 0) {
            throw new \RuntimeException('Unable to determine operations monitor run ID after insert.');
        }

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
        ];
    }

    public function updateState(
        HealthReport $report,
        ?string $alertAt,
        ?string $morningDate,
        ?string $eveningDate,
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO operations_monitor_state (
                id,
                last_status,
                last_fingerprint,
                last_alert_at,
                last_morning_report_date,
                last_evening_report_date,
                updated_at
             ) VALUES (
                1,
                :last_status,
                :last_fingerprint,
                :last_alert_at,
                :last_morning_report_date,
                :last_evening_report_date,
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                last_status = VALUES(last_status),
                last_fingerprint = VALUES(last_fingerprint),
                last_alert_at = VALUES(last_alert_at),
                last_morning_report_date = VALUES(last_morning_report_date),
                last_evening_report_date = VALUES(last_evening_report_date),
                updated_at = UTC_TIMESTAMP()"
        );
        $stmt->execute([
            'last_status' => $report->overallStatus(),
            'last_fingerprint' => $report->fingerprint(),
            'last_alert_at' => $alertAt,
            'last_morning_report_date' => $morningDate,
            'last_evening_report_date' => $eveningDate,
        ]);
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
