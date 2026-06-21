# Media Storage and Variants

Media assets now support generated variants through `media_asset_variants`.

## Variant keys

- `original`: the uploaded source file
- `thumb`: maximum 480 pixels on the longest side
- `medium`: maximum 1200 pixels on the longest side
- `large`: maximum 2000 pixels on the longest side

The upload path creates variants using `App\Tenant\Media\MediaVariantService`. If GD support or the image format is unavailable, the variant record falls back to the original file so the request path remains deterministic.

## Public URLs

```text
/media?uuid=<media-uuid>&variant=thumb
/media?uuid=<media-uuid>&variant=medium
/media?uuid=<media-uuid>&variant=large
/media?uuid=<media-uuid>&variant=original
```

Invalid variant keys fall back to `original`.

## Backfill existing media

After running migrations, backfill existing media in batches:

```bash
ARTSFOLIO_MEDIA_VARIANT_BACKFILL_LIMIT=500 php scripts/maintenance/backfill_media_variants.php
```

Repeat until `processed` returns `0`.

## Cache behavior

Media responses include long-lived public cache headers and an ETag. Because media UUIDs and storage paths are stable, replacing a file in place should be avoided. New uploads should create new media records.

<!-- End of file. -->
