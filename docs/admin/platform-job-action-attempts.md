# Platform Job Action Attempt Records

## Current behavior

Requeue and cancel actions add rows to background job attempt history.

## Operational value

This separates worker attempts from operator interventions while keeping both visible on the job detail page.

## Future requirements

```text
record acting user id
record action source route
record cancellation reason
link attempts to audit log event ids
```

<!-- End of file. -->
