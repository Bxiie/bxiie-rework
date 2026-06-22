# Operations monitor storage

`operations_monitor_runs` stores one summary and JSON snapshot per run. Migration 0045 adds `operations_monitor_metrics`, one normalized row per metric, to support efficient history and chart queries. Data is retained for 90 days through the existing run cleanup.

`operations_monitor_state.last_boot_id` stores the operating-system boot identifier. Linux reads `/proc/sys/kernel/random/boot_id`; macOS hashes `sysctl -n kern.boottime`. A changed non-empty identifier causes an immediate restart notification.
