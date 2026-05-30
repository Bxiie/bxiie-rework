# Email Outbox

## Scope

ArtsFolio now has a database-backed email outbox foundation.

## Table

```text
email_outbox
```

## Components

```text
App\Platform\Email\EmailOutboxRepository
App\Platform\Email\TemplateRenderer
App\Platform\Email\LifecycleEmailService
```

## Current supported queued emails

```text
password reset request
email verification request
welcome email
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/email_outbox.php
```

## Not implemented yet

```text
SMTP provider
Mailhog integration
email worker
retry/backoff policy
unsubscribe preferences
newsletter consent state
lifecycle schedule automation
```

<!-- End of file. -->
