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


    public function toHtml(string $subject, string $kind, string $adminUrl, array $context = []): string
    {
        $counts = $this->counts();
        $status = $this->overallStatus();
        $statusColor = match ($status) {
            HealthMetric::CRIT => '#a61b1b',
            HealthMetric::WARN => '#9a5a00',
            default => '#1f6b45',
        };
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHost = htmlspecialchars($this->hostName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeStarted = htmlspecialchars($this->startedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($adminUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logo = htmlspecialchars(rtrim((string) (getenv('ARTSFOLIO_PUBLIC_URL') ?: 'https://artsfol.io'), '/') . '/assets/logo_2.png', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $sections = [];
        foreach ([HealthMetric::CRIT, HealthMetric::WARN, HealthMetric::OK, HealthMetric::INFO] as $severity) {
            $rows = '';
            foreach ($this->metricsBySeverity(false) as $metric) {
                if ($metric->status !== $severity) {
                    continue;
                }
                $name = htmlspecialchars($metric->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $expected = htmlspecialchars($metric->expected, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $actual = htmlspecialchars($metric->actual, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $detail = htmlspecialchars($metric->detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $rows .= '<tr><td style="padding:10px;border-top:1px solid #e8e3dc;font-weight:700;vertical-align:top;">' . $name . '</td>'
                    . '<td style="padding:10px;border-top:1px solid #e8e3dc;vertical-align:top;">' . $actual . ($detail !== '' ? '<div style="margin-top:4px;color:#6c665f;font-size:13px;">' . $detail . '</div>' : '') . '</td>'
                    . '<td style="padding:10px;border-top:1px solid #e8e3dc;color:#6c665f;vertical-align:top;">' . $expected . '</td></tr>';
            }
            if ($rows === '') {
                continue;
            }
            $label = match ($severity) {
                HealthMetric::CRIT => 'Critical issues requiring attention',
                HealthMetric::WARN => 'Warnings to watch',
                HealthMetric::OK => 'Healthy checks',
                default => 'Information',
            };
            $color = match ($severity) {
                HealthMetric::CRIT => '#a61b1b',
                HealthMetric::WARN => '#9a5a00',
                HealthMetric::OK => '#1f6b45',
                default => '#4b5563',
            };
            $sections[] = '<section style="margin:22px 0;"><h2 style="margin:0 0 8px;font-size:18px;color:' . $color . ';">' . $label . '</h2>'
                . '<table role="presentation" style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e8e3dc;border-radius:10px;overflow:hidden;">'
                . '<thead><tr><th style="text-align:left;padding:9px 10px;background:#f4f1ec;">Check</th><th style="text-align:left;padding:9px 10px;background:#f4f1ec;">Actual</th><th style="text-align:left;padding:9px 10px;background:#f4f1ec;">Expected</th></tr></thead><tbody>' . $rows . '</tbody></table></section>';
        }

        $kindMessage = $kind === 'restart'
            ? '<p style="padding:12px 14px;background:#eaf2ff;border-left:5px solid #315ea8;border-radius:6px;"><strong>The ArtsFolio server restarted.</strong> This report was sent automatically after a new boot was detected.</p>'
            : '';
        if ($kind === 'component_start') {
            $started = array_values(array_filter(array_map('strval', $context['started_components'] ?? [])));
            $items = '';
            foreach ($started as $component) {
                $items .= '<li style="margin:4px 0;">' . htmlspecialchars($component, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
            }
            $kindMessage = '<div style="padding:12px 14px;background:#eaf7ef;border-left:5px solid #1f6b45;border-radius:6px;"><strong>Application component started.</strong>'
                . ($items !== '' ? '<ul style="margin:8px 0 0;padding-left:22px;">' . $items . '</ul>' : '') . '</div>';
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeSubject . '</title></head>'
            . '<body style="margin:0;background:#f5f2ed;color:#252321;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="max-width:820px;margin:0 auto;padding:28px 16px;"><div style="background:#fff;border:1px solid #e1dbd2;border-radius:16px;padding:26px;">'
            . '<img src="' . $logo . '" alt="ArtsFolio" width="170" style="display:block;width:170px;height:auto;margin-bottom:20px;">'
            . '<h1 style="margin:0 0 8px;font-size:26px;">ArtsFolio system health</h1>'
            . '<p style="margin:0 0 18px;color:#67615a;">' . $safeHost . ' · ' . $safeStarted . ' · completed in ' . number_format($this->durationSeconds, 2) . ' seconds</p>'
            . $kindMessage
            . '<div style="padding:16px;border-radius:10px;background:' . $statusColor . ';color:#fff;"><strong style="font-size:22px;">' . htmlspecialchars($status) . '</strong>'
            . '<div style="margin-top:7px;">' . (int) $counts[HealthMetric::CRIT] . ' critical · ' . (int) $counts[HealthMetric::WARN] . ' warning · ' . (int) $counts[HealthMetric::OK] . ' healthy · ' . (int) $counts[HealthMetric::INFO] . ' informational</div></div>'
            . implode('', $sections)
            . '<p style="margin:24px 0 0;"><a href="' . $safeUrl . '" style="display:inline-block;background:#312f2b;color:#fff;text-decoration:none;padding:12px 17px;border-radius:8px;font-weight:700;">Open operations dashboard</a></p>'
            . '<p style="margin:18px 0 0;color:#746f67;font-size:12px;">This dashboard requires an authenticated ArtsFolio platform-admin account. Forwarding the URL does not grant access.</p>'
            . '</div></div></body></html>';
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
