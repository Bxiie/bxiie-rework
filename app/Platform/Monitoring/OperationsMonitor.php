<?php

declare(strict_types=1);

namespace App\Platform\Monitoring;

use PDO;
use Throwable;

/**
 * Collects server, database, application, worker, and network health metrics.
 */
final class OperationsMonitor
{
    private array $metrics = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $root,
    ) {
    }

    public function run(): HealthReport
    {
        $started = microtime(true);
        $this->metrics = [];

        $this->collectServerMetrics();
        $this->collectServiceMetrics();
        $this->collectDatabaseMetrics();
        $this->collectApplicationMetrics();
        $this->collectQueueMetrics();
        $this->collectNetworkMetrics();

        return new HealthReport(
            $this->metrics,
            gethostname() ?: 'unknown-host',
            gmdate('Y-m-d H:i:s T'),
            microtime(true) - $started,
        );
    }

    private function collectServerMetrics(): void
    {
        $cpuCount = max(1, (int) trim((string) $this->command("getconf _NPROCESSORS_ONLN 2>/dev/null || nproc 2>/dev/null || echo 1")));
        $load = sys_getloadavg();
        $load1 = (float) ($load[0] ?? 0.0);
        $normalized = $load1 / $cpuCount;
        $this->numericMetric('server.cpu.count', $cpuCount, 1, 1, '>= 1 logical CPU', (string) $cpuCount, false);
        $this->upperMetric('server.load.1m_per_cpu', $normalized, 0.70, 1.00, '< 0.70 WARN; >= 1.00 CRIT', number_format($normalized, 2));

        $mem = $this->parseMemInfo();
        $total = (float) ($mem['MemTotal'] ?? 0);
        $available = (float) ($mem['MemAvailable'] ?? 0);
        $usedPct = $total > 0 ? (($total - $available) / $total) * 100 : 0;
        $this->upperMetric('server.memory.used_percent', $usedPct, 80, 90, '< 80% WARN; >= 90% CRIT', number_format($usedPct, 1) . '%', sprintf('%.1f GiB total, %.1f GiB available', $total / 1048576, $available / 1048576));

        $swapTotal = (float) ($mem['SwapTotal'] ?? 0);
        $swapFree = (float) ($mem['SwapFree'] ?? 0);
        $swapPct = $swapTotal > 0 ? (($swapTotal - $swapFree) / $swapTotal) * 100 : 0;
        $this->upperMetric('server.swap.used_percent', $swapPct, 25, 60, '< 25% WARN; >= 60% CRIT', number_format($swapPct, 1) . '%', $swapTotal > 0 ? sprintf('%.1f GiB total', $swapTotal / 1048576) : 'swap disabled');

        foreach (['/' => 'server.disk.root.used_percent', '/var/lib/mysql' => 'server.disk.mysql.used_percent'] as $path => $name) {
            $totalBytes = @disk_total_space($path);
            $freeBytes = @disk_free_space($path);
            if ($totalBytes === false || $freeBytes === false || $totalBytes <= 0) {
                $this->add($name, HealthMetric::CRIT, '< 80% WARN; >= 90% CRIT', 'unavailable', "Unable to read {$path}");
                continue;
            }
            $pct = (($totalBytes - $freeBytes) / $totalBytes) * 100;
            $this->upperMetric($name, $pct, 80, 90, '< 80% WARN; >= 90% CRIT', number_format($pct, 1) . '%', sprintf('%.1f GiB free at %s', $freeBytes / 1073741824, $path));
        }

        $inodeLine = trim((string) $this->command("df -Pi / | awk 'NR==2 {print \$5}'"));
        $inodePct = (float) rtrim($inodeLine, '%');
        $this->upperMetric('server.inodes.root.used_percent', $inodePct, 80, 90, '< 80% WARN; >= 90% CRIT', number_format($inodePct, 1) . '%');

        $uptime = (float) trim((string) @file_get_contents('/proc/uptime'));
        $this->add('server.uptime', $uptime >= 300 ? HealthMetric::OK : HealthMetric::WARN, '>= 300 seconds', $this->formatSeconds((int) $uptime), $uptime < 300 ? 'Host restarted recently.' : '');

        $ntp = trim((string) $this->command("timedatectl show -p NTPSynchronized --value 2>/dev/null || echo unknown"));
        $this->add('server.clock.ntp_synchronized', $ntp === 'yes' ? HealthMetric::OK : ($ntp === 'unknown' ? HealthMetric::INFO : HealthMetric::WARN), 'yes', $ntp);

        $this->add('server.reboot_required', file_exists('/var/run/reboot-required') ? HealthMetric::WARN : HealthMetric::OK, 'no', file_exists('/var/run/reboot-required') ? 'yes' : 'no');
    }

    private function collectServiceMetrics(): void
    {
        $services = $this->configuredServices();
        foreach ($services as $service) {
            $actual = trim((string) $this->command('systemctl is-active ' . escapeshellarg($service) . ' 2>/dev/null || true'));
            $this->add('service.' . str_replace(['@', '.'], ['_', '_'], $service), $actual === 'active' ? HealthMetric::OK : HealthMetric::CRIT, 'active', $actual !== '' ? $actual : 'unknown', $service);
        }
    }

    private function collectDatabaseMetrics(): void
    {
        $started = microtime(true);
        try {
            $this->pdo->query('SELECT 1')->fetchColumn();
            $latencyMs = (microtime(true) - $started) * 1000;
            $this->upperMetric('database.connection.latency_ms', $latencyMs, 250, 1000, '< 250ms WARN; >= 1000ms CRIT', number_format($latencyMs, 1) . 'ms');
        } catch (Throwable $e) {
            $this->add('database.connection', HealthMetric::CRIT, 'successful', 'failed', $e->getMessage());
            return;
        }

        $variables = $this->databaseVariables(['max_connections', 'tmpdir']);
        $status = $this->databaseStatus(['Threads_connected', 'Threads_running', 'Slow_queries', 'Aborted_connects', 'Uptime']);
        $maxConnections = max(1, (int) ($variables['max_connections'] ?? 1));
        $connected = (int) ($status['Threads_connected'] ?? 0);
        $connectionPct = ($connected / $maxConnections) * 100;
        $this->upperMetric('database.connections.used_percent', $connectionPct, 70, 90, '< 70% WARN; >= 90% CRIT', number_format($connectionPct, 1) . '%', "{$connected}/{$maxConnections} connected");
        $this->add('database.threads.running', HealthMetric::INFO, 'informational', (string) ((int) ($status['Threads_running'] ?? 0)));
        $this->add('database.slow_queries.total', HealthMetric::INFO, 'informational cumulative counter', (string) ((int) ($status['Slow_queries'] ?? 0)));
        $this->add('database.aborted_connects.total', HealthMetric::INFO, 'informational cumulative counter', (string) ((int) ($status['Aborted_connects'] ?? 0)));

        $databaseName = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = :schema");
        $stmt->execute(['schema' => $databaseName]);
        $sizeBytes = (int) $stmt->fetchColumn();
        $this->add('database.size', HealthMetric::INFO, 'informational; track growth', $this->formatBytes($sizeBytes), $databaseName);

        $tmpdir = (string) ($variables['tmpdir'] ?? '/tmp');
        $tmpFree = @disk_free_space($tmpdir);
        if ($tmpFree === false) {
            $this->add('database.tmpdir.free_space', HealthMetric::WARN, '>= 2 GiB WARN; < 1 GiB CRIT', 'unavailable', $tmpdir);
        } else {
            $freeGiB = $tmpFree / 1073741824;
            $statusValue = $freeGiB < 1 ? HealthMetric::CRIT : ($freeGiB < 2 ? HealthMetric::WARN : HealthMetric::OK);
            $this->add('database.tmpdir.free_space', $statusValue, '>= 2 GiB WARN; < 1 GiB CRIT', number_format($freeGiB, 2) . ' GiB', $tmpdir);
        }

        $pending = $this->pendingMigrations();
        $this->add('database.migrations.pending', $pending === 0 ? HealthMetric::OK : HealthMetric::CRIT, '0', (string) $pending);
        $checksumProblems = $this->migrationChecksumProblems();
        $this->add('database.migrations.checksum_problems', $checksumProblems === 0 ? HealthMetric::OK : HealthMetric::CRIT, '0', (string) $checksumProblems);
    }

    private function collectApplicationMetrics(): void
    {
        $queries = [
            'application.tenants.total' => "SELECT COUNT(*) FROM tenants WHERE status <> 'deleted'",
            'application.tenants.active' => "SELECT COUNT(*) FROM tenants WHERE status = 'active'",
            'application.users.total' => "SELECT COUNT(*) FROM users WHERE status <> 'deleted'",
            'application.artworks.total' => "SELECT COUNT(*) FROM artworks",
            'application.artworks.published' => "SELECT COUNT(*) FROM artworks WHERE status = 'published'",
            'application.media_assets.total' => "SELECT COUNT(*) FROM media_assets",
            'application.domains.active' => "SELECT COUNT(*) FROM tenant_domains WHERE status = 'active'",
            'application.email_signups.total' => "SELECT COUNT(*) FROM email_signups",
            'application.contact_messages.open' => "SELECT COUNT(*) FROM contact_messages WHERE status IN ('new','read')",
            'application.sales_orders.total' => "SELECT COUNT(*) FROM sales_orders",
            'application.sales_orders.paid' => "SELECT COUNT(*) FROM sales_orders WHERE payment_status IN ('paid','complete','completed','succeeded','payment_succeeded')",
            'application.analytics_events.total' => "SELECT COUNT(*) FROM analytics_events",
        ];

        foreach ($queries as $name => $sql) {
            $this->safeCountMetric($name, $sql);
        }

        $latestRollupCompletedAt = $this->safeScalar("SELECT MAX(completed_at) FROM background_jobs WHERE job_type = 'analytics.rollup' AND status = 'complete'");
        if ($latestRollupCompletedAt === null || $latestRollupCompletedAt === '') {
            $this->add('application.analytics_rollup.age_minutes', HealthMetric::WARN, '< 15 minutes WARN; >= 60 CRIT', 'no successful rollup job');
        } else {
            $age = (int) ($this->safeScalar("SELECT GREATEST(0, TIMESTAMPDIFF(MINUTE, MAX(completed_at), CURRENT_TIMESTAMP)) FROM background_jobs WHERE job_type = 'analytics.rollup' AND status = 'complete'") ?? 0);
            $status = $age >= 60 ? HealthMetric::CRIT : ($age >= 15 ? HealthMetric::WARN : HealthMetric::OK);
            $this->add('application.analytics_rollup.age_minutes', $status, '< 15 minutes WARN; >= 60 CRIT', $age . ' minutes', (string) $latestRollupCompletedAt . ' database time');
        }

        $expiredReservations = (int) ($this->safeScalar("SELECT COUNT(*) FROM sales_inventory_reservations WHERE status = 'reserved' AND expires_at < UTC_TIMESTAMP()") ?? 0);
        $this->add('application.sales_reservations.expired_active', $expiredReservations > 0 ? HealthMetric::WARN : HealthMetric::OK, '0', (string) $expiredReservations);
    }

    private function collectQueueMetrics(): void
    {
        $queuedJobs = (int) ($this->safeScalar("SELECT COUNT(*) FROM background_jobs WHERE status = 'queued'") ?? 0);
        $failedJobs = (int) ($this->safeScalar("SELECT COUNT(*) FROM background_jobs WHERE status = 'failed'") ?? 0);
        $staleJobs = (int) ($this->safeScalar("SELECT COUNT(*) FROM background_jobs WHERE status = 'running' AND COALESCE(started_at, updated_at) < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE)") ?? 0);
        $oldestJobAge = (int) ($this->safeScalar("SELECT COALESCE(TIMESTAMPDIFF(MINUTE, MIN(available_at), CURRENT_TIMESTAMP), 0) FROM background_jobs WHERE status = 'queued' AND available_at <= CURRENT_TIMESTAMP") ?? 0);
        $this->countThresholdMetric('queue.jobs.queued', $queuedJobs, 100, 1000);
        $this->countThresholdMetric('queue.jobs.failed', $failedJobs, 1, 10);
        $this->add('queue.jobs.stale_running', $staleJobs > 0 ? HealthMetric::CRIT : HealthMetric::OK, '0', (string) $staleJobs);
        $this->ageThresholdMetric('queue.jobs.oldest_ready_age_minutes', $oldestJobAge, 5, 15);

        $queuedEmails = (int) ($this->safeScalar("SELECT COUNT(*) FROM email_outbox WHERE status = 'queued'") ?? 0);
        $failedEmails = (int) ($this->safeScalar("SELECT COUNT(*) FROM email_outbox WHERE status = 'failed' AND failed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)") ?? 0);
        $staleEmails = (int) ($this->safeScalar("SELECT COUNT(*) FROM email_outbox WHERE status = 'sending' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)") ?? 0);
        $oldestEmailAge = (int) ($this->safeScalar("SELECT COALESCE(TIMESTAMPDIFF(MINUTE, MIN(available_at), UTC_TIMESTAMP()), 0) FROM email_outbox WHERE status = 'queued' AND available_at <= UTC_TIMESTAMP()") ?? 0);
        $this->countThresholdMetric('queue.email.queued', $queuedEmails, 100, 1000);
        $this->countThresholdMetric('queue.email.failed_24h', $failedEmails, 1, 10);
        $this->add('queue.email.stale_sending', $staleEmails > 0 ? HealthMetric::CRIT : HealthMetric::OK, '0', (string) $staleEmails);
        $this->ageThresholdMetric('queue.email.oldest_ready_age_minutes', $oldestEmailAge, 5, 15);

        $expectedWorkers = array_values(array_filter(array_map('trim', explode(',', (string) (getenv('ARTSFOLIO_MONITOR_EXPECTED_WORKERS') ?: 'background-1,background-2,email-1,email-2')))));
        foreach ($expectedWorkers as $workerName) {
            $stmt = $this->pdo->prepare("SELECT status, last_seen_at FROM worker_heartbeats WHERE worker_name = :worker_name LIMIT 1");
            $stmt->execute(['worker_name' => $workerName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->add('worker.' . $workerName . '.heartbeat_age_seconds', HealthMetric::CRIT, '<= 75 WARN; > 150 CRIT', 'missing');
                continue;
            }
            $age = max(0, time() - strtotime((string) $row['last_seen_at'] . ' UTC'));
            $status = $age > 150 ? HealthMetric::CRIT : ($age > 75 ? HealthMetric::WARN : HealthMetric::OK);
            if (!in_array((string) $row['status'], ['alive', 'idle', 'running'], true)) {
                $status = HealthMetric::CRIT;
            }
            $this->add('worker.' . $workerName . '.heartbeat_age_seconds', $status, '<= 75 WARN; > 150 CRIT', $age . ' seconds', 'status=' . (string) $row['status']);
        }
    }

    private function collectNetworkMetrics(): void
    {
        $hosts = array_values(array_filter(array_map('trim', explode(',', (string) (getenv('ARTSFOLIO_MONITOR_HOSTS') ?: 'artsfol.io,bxiie.artsfol.io')))));
        foreach ($hosts as $host) {
            $ip = gethostbyname($host);
            $resolved = $ip !== $host;
            $this->add('network.dns.' . $host, $resolved ? HealthMetric::OK : HealthMetric::CRIT, 'resolves to an IP', $resolved ? $ip : 'unresolved');

            $http = $this->httpProbe('https://' . $host . '/');
            if ($http['status'] === 0) {
                $this->add('network.https.' . $host, HealthMetric::CRIT, 'HTTP 200-399, <1.5s WARN, >=3s CRIT', 'connection failed', $http['error']);
            } else {
                $severity = ($http['status'] >= 200 && $http['status'] < 400) ? HealthMetric::OK : HealthMetric::CRIT;
                if ($severity === HealthMetric::OK && $http['seconds'] >= 3.0) {
                    $severity = HealthMetric::CRIT;
                } elseif ($severity === HealthMetric::OK && $http['seconds'] >= 1.5) {
                    $severity = HealthMetric::WARN;
                }
                $this->add('network.https.' . $host, $severity, 'HTTP 200-399, <1.5s WARN, >=3s CRIT', sprintf('HTTP %d in %.3fs', $http['status'], $http['seconds']));
            }

            $days = $this->tlsDaysRemaining($host);
            if ($days === null) {
                $this->add('network.tls.' . $host . '.days_remaining', HealthMetric::CRIT, '>= 21 days WARN; < 7 CRIT', 'unavailable');
            } else {
                $severity = $days < 7 ? HealthMetric::CRIT : ($days < 21 ? HealthMetric::WARN : HealthMetric::OK);
                $this->add('network.tls.' . $host . '.days_remaining', $severity, '>= 21 days WARN; < 7 CRIT', $days . ' days');
            }
        }

        $route = $this->defaultRoute();
        $this->add('network.default_route', $route !== '' ? HealthMetric::OK : HealthMetric::CRIT, 'present', $route !== '' ? $route : 'missing');
    }

    private function defaultRoute(): string
    {
        $commands = [
            "ip route show default 2>/dev/null | head -1",
            "/sbin/route -n get default 2>/dev/null | awk '/gateway:|interface:/ {printf \"%s%s\", (seen++ ? \" \" : \"\"), $0}'",
            "netstat -rn 2>/dev/null | awk '$1 == \"default\" || $1 == \"0.0.0.0\" {print; exit}'",
        ];

        foreach ($commands as $command) {
            $route = trim((string) $this->command($command));
            if ($route !== '') {
                return $route;
            }
        }

        return '';
    }

    private function configuredServices(): array
    {
        $raw = getenv('ARTSFOLIO_MONITOR_SERVICES') ?: 'mariadb,php8.4-fpm,caddy,artsfolio-background-worker@1.service,artsfolio-background-worker@2.service,artsfolio-email-worker@1.service,artsfolio-email-worker@2.service';
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function databaseVariables(array $names): array
    {
        $wanted = array_fill_keys($names, true);
        $result = [];
        foreach ($this->pdo->query('SHOW VARIABLES')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string) $row['Variable_name'];
            if (isset($wanted[$name])) {
                $result[$name] = (string) $row['Value'];
            }
        }
        return $result;
    }

    private function databaseStatus(array $names): array
    {
        $wanted = array_fill_keys($names, true);
        $result = [];
        foreach ($this->pdo->query('SHOW GLOBAL STATUS')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string) $row['Variable_name'];
            if (isset($wanted[$name])) {
                $result[$name] = (string) $row['Value'];
            }
        }
        return $result;
    }

    private function pendingMigrations(): int
    {
        $files = array_map('basename', glob($this->root . '/database/migrations/*.sql') ?: []);
        $applied = array_map(static fn (array $row): string => (string) $row['migration'], $this->pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC));
        return count(array_diff($files, $applied));
    }

    private function migrationChecksumProblems(): int
    {
        if (!$this->columnExists('schema_migrations', 'checksum_sha256')) {
            return 1;
        }
        $problems = 0;
        $rows = $this->pdo->query('SELECT migration, checksum_sha256 FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $file = $this->root . '/database/migrations/' . (string) $row['migration'];
            if (!is_file($file)) {
                $problems++;
                continue;
            }
            $expected = hash_file('sha256', $file);
            if (!hash_equals($expected, (string) ($row['checksum_sha256'] ?? ''))) {
                $problems++;
            }
        }
        return $problems;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name");
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function safeCountMetric(string $name, string $sql): void
    {
        try {
            $count = (int) $this->pdo->query($sql)->fetchColumn();
            $this->add($name, HealthMetric::INFO, 'informational; watch trend', (string) $count);
        } catch (Throwable $e) {
            $this->add($name, HealthMetric::WARN, 'query succeeds', 'unavailable', $e->getMessage());
        }
    }

    private function safeScalar(string $sql): mixed
    {
        try {
            return $this->pdo->query($sql)->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    }

    private function countThresholdMetric(string $name, int $value, int $warn, int $crit): void
    {
        $status = $value >= $crit ? HealthMetric::CRIT : ($value >= $warn ? HealthMetric::WARN : HealthMetric::OK);
        $this->add($name, $status, "< {$warn} WARN; >= {$crit} CRIT", (string) $value);
    }

    private function ageThresholdMetric(string $name, int $value, int $warn, int $crit): void
    {
        $status = $value >= $crit ? HealthMetric::CRIT : ($value >= $warn ? HealthMetric::WARN : HealthMetric::OK);
        $this->add($name, $status, "< {$warn} minutes WARN; >= {$crit} CRIT", $value . ' minutes');
    }

    private function upperMetric(string $name, float $value, float $warn, float $crit, string $expected, string $actual, string $detail = ''): void
    {
        $status = $value >= $crit ? HealthMetric::CRIT : ($value >= $warn ? HealthMetric::WARN : HealthMetric::OK);
        $this->add($name, $status, $expected, $actual, $detail);
    }

    private function numericMetric(string $name, float $value, float $warn, float $crit, string $expected, string $actual, bool $upper = true): void
    {
        $status = $upper
            ? ($value >= $crit ? HealthMetric::CRIT : ($value >= $warn ? HealthMetric::WARN : HealthMetric::OK))
            : ($value < $crit ? HealthMetric::CRIT : ($value < $warn ? HealthMetric::WARN : HealthMetric::OK));
        $this->add($name, $status, $expected, $actual);
    }

    private function add(string $name, string $status, string $expected, string $actual, string $detail = ''): void
    {
        $this->metrics[] = new HealthMetric($name, $status, $expected, $actual, $detail);
    }

    private function parseMemInfo(): array
    {
        $result = [];
        foreach (@file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([A-Za-z_]+):\s+(\d+)/', $line, $matches) === 1) {
                $result[$matches[1]] = (int) $matches[2];
            }
        }
        return $result;
    }

    private function command(string $command): string
    {
        $output = shell_exec($command);
        return is_string($output) ? $output : '';
    }

    private function httpProbe(string $url): array
    {
        $command = sprintf(
            "curl -L -sS -o /dev/null --connect-timeout 5 --max-time 10 -w '%%{http_code} %%{time_total}' %s 2>&1",
            escapeshellarg($url),
        );
        $output = trim($this->command($command));
        if (preg_match('/^(\d{3})\s+([0-9.]+)$/', $output, $matches) === 1) {
            return ['status' => (int) $matches[1], 'seconds' => (float) $matches[2], 'error' => ''];
        }
        return ['status' => 0, 'seconds' => 0.0, 'error' => $output];
    }

    private function tlsDaysRemaining(string $host): ?int
    {
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client('ssl://' . $host . ':443', $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            return null;
        }
        $params = stream_context_get_params($socket);
        fclose($socket);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return null;
        }
        $parsed = openssl_x509_parse($cert);
        if (!is_array($parsed) || !isset($parsed['validTo_time_t'])) {
            return null;
        }
        return (int) floor(((int) $parsed['validTo_time_t'] - time()) / 86400);
    }

    private function formatSeconds(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return sprintf('%dd %dh %dm', $days, $hours, $minutes);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }
        return number_format($value, 2) . ' ' . $units[$unit];
    }
}

// End of file.
