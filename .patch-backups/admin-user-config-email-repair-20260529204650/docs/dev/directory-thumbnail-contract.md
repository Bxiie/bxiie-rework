# Directory thumbnail contract

The public artist directory is setting-driven. No schema migration is required for thumbnail selection.

Tenant settings used by the directory:

| Setting key | Writer | Reader | Purpose |
| --- | --- | --- | --- |
| `platform_directory_opt_in` | `Tenant\Admin\DiscoverySettingsController` | `Platform\DirectoryController` | Enables tenant listing when truthy. |
| `platform_directory_summary` | `Tenant\Admin\DiscoverySettingsController` | `Platform\DirectoryController` | Short text on the public directory card. |
| `platform_directory_thumbnail_artwork_id` | `Tenant\Admin\DiscoverySettingsController` | `Platform\DirectoryController` | Published artwork whose primary media becomes the card thumbnail. |

Validation rules:

- the artwork must belong to the same tenant,
- the artwork must have `status = 'published'`,
- `artworks.primary_media_id` must point to a row in `media_assets`,
- stale or invalid IDs are saved as an empty setting.

Diagnostic command:

```bash
php scripts/debug/check_directory_thumbnail_contract.php
```

# End of file.
