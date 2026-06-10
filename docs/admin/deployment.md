# Production Deployment Status

The production deploy command prints a final status banner at the end of every run.

Successful runs end with:

```text
== DEPLOY SUCCEEDED ==
```

Failed runs end with:

```text
== DEPLOY FAILED ==
```

The failure banner includes the failed stage, exit code, branch, and commit. Use the failed stage name to decide where to look first.

The background job worker is required. Deploy and health checks fail if `artsfolio-background-worker.service` is missing or inactive. Platform admin worker health depends on this service heartbeat, so worker failures must not be downgraded to warnings.

<!-- End of file. -->
