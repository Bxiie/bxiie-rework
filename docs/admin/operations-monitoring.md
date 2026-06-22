# ArtsFolio operations monitoring

ArtsFolio includes a five-minute operations monitor that checks server, database, application, worker, email, network, and TLS health.

## Install

After deployment and migration:

```bash
cd /var/www/artsfolio
sudo ./scripts/ops/install_monitoring_service.sh
```

The installer adds:

- `artsfolio-monitor.service`
- `artsfolio-monitor.timer`

The timer runs every five minutes. It is persistent, so a missed run is performed after reboot.

## Reports and alerts

The monitor sends direct SMTP email to every active platform owner/admin:

- daily report at 07:15 America/New_York
- daily report at 19:15 America/New_York
- immediate alert when a WARN or CRIT condition first appears
- immediate alert when the trouble set changes
- reminder every six hours for continuing WARN conditions
- reminder every hour for continuing CRIT conditions
- recovery email when status returns to OK

Direct SMTP is intentional: a failed email worker must not prevent the monitor from reporting that the email worker failed.

## Run manually

```bash
php scripts/ops/monitor_artsfolio.php
php scripts/ops/monitor_artsfolio.php --json
php scripts/ops/monitor_artsfolio.php --trouble-only
php scripts/ops/monitor_artsfolio.php --force-report
php scripts/ops/monitor_artsfolio.php --no-email
```

Every metric line includes status, metric name, expected range, actual value, and supporting detail.

## Important checks

The monitor includes:

- CPU count and normalized one-minute load
- memory and swap use
- disk and inode use
- uptime, NTP state, reboot-required state
- MariaDB, PHP-FPM, Caddy, and worker instance state
- DB connection latency and connection use
- DB size and MariaDB temporary-directory free space
- pending migrations and migration checksum drift
- tenant, user, artwork, media, domain, subscriber, contact, order, and analytics counts
- analytics rollup freshness
- queue depth, failure counts, stale work, and oldest-ready age
- expected worker heartbeat freshness
- DNS, HTTPS status/latency, TLS expiration, and default route

## Configuration

Optional environment variables in `/etc/artsfolio/artsfolio.env`:

```text
ARTSFOLIO_MONITOR_TIMEZONE=America/New_York
ARTSFOLIO_MONITOR_HOSTS=artsfol.io,bxiie.artsfol.io
ARTSFOLIO_MONITOR_SERVICES=mariadb,php8.4-fpm,caddy,artsfolio-background-worker@1.service,artsfolio-background-worker@2.service,artsfolio-email-worker@1.service,artsfolio-email-worker@2.service
ARTSFOLIO_MONITOR_EXPECTED_WORKERS=background-1,background-2,email-1,email-2
ARTSFOLIO_MONITOR_WARN_REMINDER_MINUTES=360
ARTSFOLIO_MONITOR_CRIT_REMINDER_MINUTES=60
```

## Inspect

```bash
systemctl status artsfolio-monitor.timer --no-pager
systemctl status artsfolio-monitor.service --no-pager
systemctl list-timers artsfolio-monitor.timer --no-pager
journalctl -u artsfolio-monitor.service -n 200 --no-pager
```

The last 90 days of reports are stored in `operations_monitor_runs`. Notification suppression state is stored in `operations_monitor_state`.

## Alert subject and metric order

- Reports containing any critical condition use an email subject beginning with `[ArtsFolio CRITICAL]`.
- Reports containing warnings but no critical condition begin with `[ArtsFolio WARNING]`.
- Recovery notices begin with `[ArtsFolio RECOVERY]`.
- Email and console metric lists are ordered CRIT, WARN, OK, then INFO so urgent conditions appear first.
- Dry-run delivery is identified explicitly and is never reported as a sent message.

## Application component start notifications

The operations monitor stores the last observed state of each monitored application component. It sends an immediate email when a component transitions from stopped, stale, or unavailable to healthy running state. Monitored components include MariaDB, PHP-FPM, Caddy, background workers, and email workers.

The notification subject begins with `[ArtsFolio COMPONENT STARTED]` and the email names every component that started. The initial monitor run establishes a baseline and does not announce every already-running component. A `--no-email` run does not consume a pending component-start event.

## Deployment component-start email

After a successful production health check, `scripts/deploy/deploy_production.sh` invokes the monitor with an explicit list of the components it restarted. This avoids missing short restart windows between five-minute monitor polls. Email delivery failure makes the deploy fail at the `Component start notification` stage.
