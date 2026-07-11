# Editing email templates

Platform owners and platform administrators can edit every filesystem-backed email template.

1. Click **Email Templates** in the platform-admin navigation.
2. Select a template from the list.
3. Edit the body while preserving any required `{{ placeholder }}` tokens.
4. Click **Save email template**.

Changes apply only when future messages are rendered and queued. Email already present in the outbox keeps its stored subject and body.

The editor does not create, rename, or delete templates. Those structural changes remain source-controlled development work.

## Safety

- Only existing `.txt`, `.md`, and `.html` files beneath `template/email/` are editable.
- Symlinks and macOS metadata files are excluded.
- Saves require a valid CSRF token and platform owner or administrator access.
- Each changed template produces a `platform.email_template.updated` audit event containing old and new checksums and byte counts.
- Template bodies are limited to 256 KiB.

<!-- End of file. -->
