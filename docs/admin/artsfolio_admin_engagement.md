
## Contact and email signup source details

Tenant contact messages and public email-list signups retain the submitter IP address, user agent, and coarse location fields when available: city, region, and country. Tenant admins can see the IP and location in the contact-message and email-signup admin tables and CSV exports.

Public tenant contact and signup forms use the Cloudflare Turnstile widget. The platform-level `/contact` page uses the same first-party widget so it does not depend on Google Cloudflare Turnstile domain configuration.

## Tenant user management

Tenant admins can invite additional tenant admins. Tenant owners can also promote tenant admins to tenant owner and delete invited or active tenant users from the tenant. Delete actions require a browser confirmation and a server-side `DELETE` confirmation token, revoke tenant-scoped roles and sessions, and write a tenant audit-log row.

# End of file.
