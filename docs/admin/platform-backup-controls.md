# Platform backup controls

Platform owners and administrators can queue an immediate monitor run from
**System Operations**. The **Backups** page shows the latest hourly backup,
weekly repository verification, monthly restore test, and timer details.

The buttons queue existing systemd jobs. They do not run backup commands inside
the browser request. Platform support users may view results but cannot start
jobs. Every manual start is recorded in the platform audit log.

<!-- End of file. -->
