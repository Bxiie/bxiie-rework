# Platform email-template editor

The platform-admin editor is available at:

- `GET /platform/admin/email-templates`
- `POST /platform/admin/email-templates`

`EmailTemplatesController` builds a fresh allowlist by recursively enumerating canonical files beneath `template/email/`. Only existing `.txt`, `.md`, and `.html` files are accepted. A submitted path must match an allowlisted relative path exactly.

Security controls include platform owner/admin authorization, CSRF validation, symlink exclusion, canonical root containment, NUL rejection, a 256 KiB size limit, and atomic same-directory replacement.

The filesystem remains the source of truth. Changes made through the UI therefore modify the deployed checkout and should be captured back into source control when they are intended to survive a future deployment.

Audit action: `platform.email_template.updated`.

Regression coverage: `scripts/test/platform_email_templates_admin_static.php`.

<!-- End of file. -->
