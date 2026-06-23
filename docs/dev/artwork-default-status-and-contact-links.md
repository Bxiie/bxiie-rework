# Artwork default status and contact image links

`new_artwork_default_status` is stored in `tenant_settings` with allowed values `draft` and `published`. `Tenant\Admin\SettingsController` renders and validates the setting on the Miscellaneous settings subpage. `ArtworkUploadController` resolves the tenant-scoped value and passes it to `ArtworkUploadService`, which writes the status in the initial artwork insert.

Artwork contact submissions post only an artwork slug. `Tenant\ContactController` resolves a published tenant-owned artwork and its primary media UUID, builds the public `/media?uuid=...` URL from the active request host, and appends it to the message before persistence and notification. Visitor-supplied image URLs are not trusted.

The tenant Artworks index uses the same responsive action-card pattern as Portfolio Sections for upload, placement, and ordering workflows.

The public platform contact form now records a normalized topic, retains entered values after validation failures, uses a single valid class attribute, and includes the topic in both the stored subject and queued notification email.

No schema migration is required. Existing tenant settings remain compatible and default to `draft` when the key is absent or invalid.

<!-- End of file. -->
