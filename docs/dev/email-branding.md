# Email branding

All queued ArtsFolio email is passed through `App\Platform\Email\EmailOutboxRepository::queue()` before delivery. The repository applies `App\Platform\Email\BrandedEmail` so account messages, tenant notifications, lifecycle emails, platform signup-code invites, and tenant-admin invites share the same ArtsFolio header and footer.

Templates should contain message-specific content only. Do not duplicate the ArtsFolio header, footer, copyright line, or platform URL inside individual templates unless the centralized shell is intentionally retired.

Regression test:

```bash
php scripts/test/email_branding_static.php
```

<!-- End of file. -->
