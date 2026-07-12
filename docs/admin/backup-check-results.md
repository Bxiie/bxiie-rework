## Backup check result behavior

The System Operations backup cards are fed by three status documents under
`/var/lib/artsfolio/backup-status`. Hourly backup, weekly repository check, and
monthly restore jobs update those files atomically.

Weekly and monthly jobs send a forced health report to active platform
administrators. A report may exit with code 1 or 2 when the platform itself is
warning or critical; that does not mean email delivery failed. Exit codes above
2 indicate a reporting-process failure and fail the scheduled service.

<!-- End of backup check result behavior. -->
