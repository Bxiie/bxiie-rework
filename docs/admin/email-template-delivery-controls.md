# Email template descriptions and delivery controls

Platform Admin > Email Templates shows a purpose description and delivery status for every discovered template.

- **Active** permits future messages of that template type to enter the outbox.
- **Suppressed** prevents future messages of that template type from being queued.

Already queued messages are not removed. Suppressing password-reset, verification, invitation, payment-failure, or billing emails can block essential workflows. Re-enable the template before testing those workflows.

Status changes are audited as `platform.email_template.status_updated`.

<!-- End of file. -->
