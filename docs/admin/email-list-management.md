# Tenant email list management

Tenant admins can manage email-list addresses from **Tenant Admin → Email Signups**.

Available actions:

- Search by email, name, source, notes, or consent status.
- Sort by email, name, source, consent status, created date, or updated date.
- Edit name, source, and notes inline.
- Confirm or unsubscribe an address.
- Delete an address from the tenant list.
- Export the current filtered list as CSV.
- Import CSV files with `email`, `name`, `source`, `notes`, and `consent_status` headers.

Deleting an address removes the row from `email_signups`. Use unsubscribe instead when the tenant needs to preserve opt-out history.

<!-- End of file. -->
