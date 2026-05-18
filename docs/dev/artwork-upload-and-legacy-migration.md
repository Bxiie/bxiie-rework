# Artwork Upload and Legacy Bxiie Migration

## Artwork upload MVP

Tenant admins can use:

```text
/admin/artwork/upload
```

Uploads are staged under:

```text
storage/uploads/artwork/<tenant-slug>
```

A JSONL sidecar manifest is written at:

```text
storage/uploads/artwork/<tenant-slug>/manifest.jsonl
```

## Legacy Bxiie migration

Inventory a mirrored copy of the old site:

```bash
php scripts/migration/inventory_legacy_bxiie.php --source=/path/to/legacy/bxiie
```

Stage images:

```bash
php scripts/migration/stage_legacy_bxiie_images.php --inventory=storage/imports/bxiie-legacy-inventory.json --tenant=bxiie
```

## Rule

Stage first, review mappings second, publish third. Do not guess final artwork metadata from filenames.

<!-- End of file. -->

## Database-backed upload behavior

Artwork uploads now write:

```text
media_assets
artworks
```

Field mapping:

```text
title          -> artworks.title, media_assets.title, media_assets.alt_text
date/year      -> artworks.year_created
medium         -> artworks.medium
notes          -> artworks.description, media_assets.caption
sale status    -> artworks.sale_status
price          -> artworks.price
image file     -> media_assets.storage_path, artworks.primary_media_id
```

New uploads default to:

```text
artworks.status = draft
```

<!-- End of file. -->
