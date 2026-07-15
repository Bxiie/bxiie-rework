# Training tenant engagement fixtures

The repository contains deterministic demonstration records for the tenant whose slug is exactly `training`.

## Contents

The fixture set creates:

- Eight event records in `exhibitions`.
- Three fictional contact messages in `contact_messages`.
- Four fictional mailing-list records in `email_signups`.

The addresses use `example.com`, and the IP addresses use documentation-only network ranges. They must not be replaced with real customer data.

## Deploy after a Git pull

Run on production:

```bash
cd /var/www/artsfolio

ARTSFOLIO_ROOT=/var/www/artsfolio \
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env \
bash ./scripts/training/deploy_training_engagement.sh
```

The PHP seeder:

1. Requires exactly one tenant with slug `training`.
2. Validates required database columns before changing data.
3. Saves the tenant's current event, contact-message, and signup rows under `.update-backups/training-engagement-git-<UTC timestamp>/`.
4. Replaces only the named fixtures inside one database transaction.
5. Verifies counts of eight events, three messages, and four signups.

It is safe to rerun when the standard training fixture state must be restored.

## Roll back only the named fixtures

```bash
cd /var/www/artsfolio

ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env \
php ./scripts/training/rollback_training_engagement.php
```

The rollback does not automatically restore the pre-deployment JSON backup. It removes only the deterministic fixture rows. The JSON backup is retained for inspection or manual recovery.

## Tests

```bash
php -l scripts/training/seed_training_engagement.php
php -l scripts/training/rollback_training_engagement.php
bash -n scripts/training/deploy_training_engagement.sh
php scripts/test/training_engagement_fixtures_static.php
./scripts/test/preflight.sh
```

# End of file.
