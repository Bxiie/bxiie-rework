# Bxiie Content Migration

## Spreadsheet source

The migration spreadsheet should be stored at:

```text
storage/imports/site_images.xlsx
```

## Legacy image inventory

Create the legacy image inventory:

```bash
php scripts/migration/inventory_legacy_bxiie.php --source=../bxiie
```

## Match spreadsheet to legacy files

```bash
php scripts/migration/audit_bxiie_spreadsheet_matches.php \
  --xlsx=storage/imports/site_images.xlsx \
  --inventory=storage/imports/bxiie-legacy-inventory.json
```

The audit writes:

```text
storage/imports/bxiie-spreadsheet-match-audit.json
```

Do not run the final importer until the match audit is clean enough to trust.

<!-- End of file. -->

## Import matched spreadsheet artwork

Dry run:

```bash
ARTSFOLIO_ENV_FILE=.env.local php scripts/migration/import_bxiie_site_images.php \
  --tenant=bxiie \
  --host=bxiie.com \
  --xlsx=storage/imports/site_images.xlsx \
  --audit=storage/imports/bxiie-spreadsheet-match-audit.json \
  --source=../bxiie \
  --dry-run=1
```

Real import:

```bash
ARTSFOLIO_ENV_FILE=.env.local php scripts/migration/import_bxiie_site_images.php \
  --tenant=bxiie \
  --host=bxiie.com \
  --xlsx=storage/imports/site_images.xlsx \
  --audit=storage/imports/bxiie-spreadsheet-match-audit.json \
  --source=../bxiie
```

The importer publishes matched spreadsheet rows, creates media records, creates portfolio sections, and assigns works to sections.

