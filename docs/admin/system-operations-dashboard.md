# System Operations dashboard

Platform owners, administrators, and support users can open `/platform/admin/operations`. The URL can be copied to another administrator, but authentication and platform role checks still apply.

The overview shows the latest value, expected range, state, and seven-day trend for every saved monitor metric. Open a metric for 1, 7, 14, 30, or 90-day history, or open an individual run for the complete check report.

Monitor email is HTML formatted with critical checks first, followed by warnings, healthy checks, and informational checks. The email links back to this dashboard. A restart email is always sent after the monitor detects a changed operating-system boot identifier.

## Backup protection metrics

The dashboard and status emails include hourly off-site snapshot freshness and duration, database dump size, total Restic repository size, weekly integrity-check freshness and result, monthly restore-test freshness and result, and the enabled state of all backup timers.

The weekly and monthly systemd jobs force a status email after recording their result. Recipients are the active platform owners and administrators already used by the operations monitor. A failed check is stored before the job exits nonzero, preserving both the systemd failure and the dashboard/email signal.

# End of file.

## Backup credentials and verification scope

Platform owners and platform administrators configure off-site backup access under **Platform Settings → Off-site backups**. Saved secret values are never redisplayed. The System Operations page reports backup freshness and test results but does not reveal repository credentials.

Changing the Restic password does not re-encrypt an existing repository. Enter the password that was used when the repository was initialized. Replacing it with an unrelated value causes all hourly, weekly, and monthly jobs to fail until the original password is restored or a new repository is initialized.

<!-- End of backup credentials and verification scope. -->
