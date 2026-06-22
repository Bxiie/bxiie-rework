# Artwork default status and contact image links

`new_artwork_default_status` is stored in `tenant_settings` with allowed values `draft` and `published`. `ArtworkUploadController` resolves the tenant-scoped value and passes it to `ArtworkUploadService`, which writes the status in the initial artwork insert.

Artwork contact submissions post only an artwork slug. `Tenant\ContactController` resolves a published tenant-owned artwork and its primary media UUID, builds the public `/media?uuid=...` URL from the active request host, and appends it to the message before persistence and notification.

No schema migration is required.

<!-- End of file. -->
