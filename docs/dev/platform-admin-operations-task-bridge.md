# Platform Admin operations task bridge

The application invokes `/usr/bin/sudo -n /usr/local/sbin/artsfolio-admin-task
ACTION`. The root-owned helper accepts four literal actions and maps them to
existing systemd units. It never accepts a user-provided unit name or arbitrary
command.

The production apply script installs the helper and a narrowly scoped sudoers
rule. Backup status JSON contains no secrets and is made readable by PHP-FPM.

<!-- End of file. -->
