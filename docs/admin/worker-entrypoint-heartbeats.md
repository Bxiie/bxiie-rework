# Worker Entrypoint Heartbeats

## Current behavior

Worker scripts can report heartbeat rows before running work.

## Operational value

The platform admin Workers screen can show whether worker entrypoints have recently executed.

## Future requirements

```text
heartbeat at start and finish
heartbeat around each job attempt
stale worker detection
worker process supervisor integration
```

<!-- End of file. -->
