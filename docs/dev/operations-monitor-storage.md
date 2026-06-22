# Operations monitor storage

`operations_monitor_runs` stores one summary and JSON snapshot per run. Migration 0045 adds `operations_monitor_metrics`, one normalized row per metric, to support efficient history and chart queries. Data is retained for 90 days through the existing run cleanup.

`operations_monitor_state.last_boot_id` stores the operating-system boot identifier. Linux reads `/proc/sys/kernel/random/boot_id`; macOS hashes `sysctl -n kern.boottime`. A changed non-empty identifier causes an immediate restart notification.

## Component state transitions

`operations_monitor_state.last_component_states_json` stores the most recent running/stopped state for monitored services and worker heartbeats. The monitor compares the new report with this state and emits a `component_start` notification for transitions into healthy running state. State is updated only after delivery is permitted; `--no-email` preserves a pending transition for the next normal timer run.

## Deployment component-start email

After a successful production health check, `scripts/deploy/deploy_production.sh` invokes the monitor with an explicit list of the components it restarted. This avoids missing short restart windows between five-minute monitor polls. Email delivery failure makes the deploy fail at the `Component start notification` stage.
