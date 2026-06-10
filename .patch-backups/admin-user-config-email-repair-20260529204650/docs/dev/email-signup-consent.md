# Email Signup Consent

Tenant admins can update email signup consent status through:

```text
POST /admin/email-signups/consent
```

Allowed values:

```text
pending
confirmed
unsubscribed
```

Manual verification:

```bash
php scripts/test/email_signup_consent.php
php scripts/test/tenant_admin_lists.php
```

<!-- End of file. -->
