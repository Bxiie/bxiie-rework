# Email signup duplicate notification guard

Public tenant email-list signup handling checks for an existing active signup before queueing a tenant-admin notification email.

Active means `consent_status` is `pending` or `confirmed`. A repeat submission for an active address may update metadata such as name, source, IP, user agent, and location, but it must not queue another `tenant.signup_notification` email.

Unsubscribed addresses are not active. A new public submission for an unsubscribed address may restore the row to `pending` and queue a tenant-admin notification.

The repository upsert intentionally preserves `pending` and `confirmed` consent states so a repeat public signup cannot downgrade a confirmed address back to pending.

<!-- End of file. -->
