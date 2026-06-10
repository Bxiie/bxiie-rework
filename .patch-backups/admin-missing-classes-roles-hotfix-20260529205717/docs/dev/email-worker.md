# Email Worker

## Scope

The email worker currently dry-runs queued email delivery.

It does not contact SMTP, Mailhog, or any external provider.

## Worker command

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/workers/email_run_once.php
```

## Status command

```bash
php scripts/test/email_outbox_status.php
```

## Manual verification

Queue emails:

```bash
php scripts/test/email_outbox.php
```

Process one queued email:

```bash
php scripts/workers/email_run_once.php
```

Inspect status:

```bash
php scripts/test/email_outbox_status.php
```

Expected:

```text
queued -> sending -> sent
```

## Production gap

Before real delivery:

```text
configure provider
add SMTP/Mailhog sender
add retry policy
add failure classification
add bounce handling
add unsubscribe handling
add tenant sender policy
```

<!-- End of file. -->
