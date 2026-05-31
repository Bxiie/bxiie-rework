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

The failure banner includes the failed stage, exit code, branch, and commit. Use the failed stage name to decide where to look first. For example, if the failed stage is `Preflight`, review the test output directly above the banner before restarting services manually.

<!-- End of file. -->
