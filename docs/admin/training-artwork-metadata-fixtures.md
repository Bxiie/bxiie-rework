# Training artwork metadata fixtures

The training artwork metadata fixture normalizes the 14 Northstar Studio sample
artworks into a repeatable demonstration matrix.

## Safety

- The tenant is resolved by slug `training`.
- Dry-run is the default.
- Database writes require the explicit `--apply` argument.
- A JSON backup is completed before the transaction begins.
- The transaction rolls back on any update or verification failure.
- The six site-type branding artworks are left unchanged. They remain available
  for site identity, About, Contact, header, and watermark use.

## Demonstration coverage

The fixture provides:

- published, draft, and archived artwork;
- for-sale, sold, and not-for-sale states;
- one-off, limited-quantity, and variant-inventory sale models;
- flat-per-order, capped, free, variant-level, and quoted shipping;
- enabled and disabled checkout;
- single-section, multi-section, and no-section assignments;
- complete media accessibility metadata;
- an intentional missing-primary-image case;
- queued, reviewing, published, and declined curation examples when an active
  tenant user is available.

## Commands

Dry-run:

```bash
ARTSFOLIO_ROOT=/var/www/artsfolio \
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env \
ARTSFOLIO_TRAINING_BACKUP_ROOT=/var/lib/artsfolio/training-backups \
bash ./scripts/training/deploy_training_artwork_metadata.sh --dry-run
```

Apply:

```bash
ARTSFOLIO_ROOT=/var/www/artsfolio \
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env \
ARTSFOLIO_TRAINING_BACKUP_ROOT=/var/lib/artsfolio/training-backups \
bash ./scripts/training/deploy_training_artwork_metadata.sh --apply
```


## Fixture scope

The fixture changes only behavior that can currently be managed through the
tenant interface. It does not create or remove homepage artwork assignments,
because the tenant interface does not currently provide homepage-artwork
assignment controls.

The six branding records are site-type artworks. Their status, sale metadata,
type assignments, and media relationships are preserved exactly as configured.

# End of file.
